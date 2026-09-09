package main

import (
	"bufio"
	"bytes"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strconv"
	"strings"
	"sync"
	"time"
)

const maxRequestBody = 2 << 20

type Config struct {
	Addr         string
	Token        string
	StateDir     string
	KrunaiBin    string
	ModelBaseURL string
	ModelAPIKey  string
	DefaultModel string
}

type Runtime struct {
	ExternalID     string         `json:"external_id"`
	IdempotencyKey string         `json:"idempotency_key"`
	BotID          string         `json:"bot_id"`
	VMName         string         `json:"vm_name"`
	WorkDir        string         `json:"work_dir"`
	State          string         `json:"state"`
	VCPU           int            `json:"vcpu"`
	MemoryMB       int            `json:"memory_mb"`
	DiskGB         int            `json:"disk_gb"`
	Image          string         `json:"image"`
	Endpoint       string         `json:"endpoint,omitempty"`
	Generation     uint64         `json:"generation"`
	NetworkPolicy  map[string]any `json:"network_policy,omitempty"`
	CreatedAt      time.Time      `json:"created_at"`
	UpdatedAt      time.Time      `json:"updated_at"`
}

type Store struct {
	mu       sync.RWMutex
	path     string
	Runtimes map[string]*Runtime `json:"runtimes"`
}

type Server struct {
	cfg   Config
	store *Store
	mux   *http.ServeMux
}

type createRuntimeRequest struct {
	IdempotencyKey string         `json:"idempotency_key"`
	BotID          string         `json:"bot_id"`
	VCPU           int            `json:"vcpu"`
	MemoryMB       int            `json:"memory_mb"`
	DiskGB         int            `json:"disk_gb"`
	Image          string         `json:"image"`
	NetworkPolicy  map[string]any `json:"network_policy"`
}

type turnRequest struct {
	BotID           string         `json:"bot_id"`
	ConversationID  any            `json:"conversation_id"`
	Input            string         `json:"input"`
	OutputMessageID  string         `json:"output_message_id"`
	Model            string         `json:"model"`
	SystemPrompt     string         `json:"system_prompt"`
	Tools            []string       `json:"tools"`
	MemoryEnabled    bool           `json:"memory_enabled"`
	NetworkPolicy    map[string]any `json:"network_policy"`
	Callbacks        struct {
		Chunks   string `json:"chunks"`
		Complete string `json:"complete"`
	} `json:"callbacks"`
}

type runtimeEvent struct {
	Type       string         `json:"type"`
	Sequence   int            `json:"sequence,omitempty"`
	Kind       string         `json:"kind,omitempty"`
	Delta      string         `json:"delta,omitempty"`
	TokenCount *int           `json:"token_count,omitempty"`
	Metadata   map[string]any `json:"metadata,omitempty"`
	Event      string         `json:"event,omitempty"`
	Tool       string         `json:"tool,omitempty"`
	Error      string         `json:"error,omitempty"`
}

func main() {
	cfg, err := loadConfig()
	if err != nil {
		log.Fatal(err)
	}
	store, err := loadStore(filepath.Join(cfg.StateDir, "state.json"))
	if err != nil {
		log.Fatal(err)
	}
	server := newServer(cfg, store)
	log.Printf("tinyvm-gateway listening on %s (%s/%s)", cfg.Addr, runtime.GOOS, runtime.GOARCH)
	if err := http.ListenAndServe(cfg.Addr, server); err != nil {
		log.Fatal(err)
	}
}

func loadConfig() (Config, error) {
	home, err := os.UserHomeDir()
	if err != nil {
		return Config{}, err
	}
	cfg := Config{
		Addr:         getenv("GROKBOT_GATEWAY_ADDR", "127.0.0.1:8787"),
		Token:        os.Getenv("GROKBOT_GATEWAY_TOKEN"),
		StateDir:     getenv("GROKBOT_STATE_DIR", filepath.Join(home, ".grokbot", "tinyvm-gateway")),
		KrunaiBin:    getenv("KRUNAI_BIN", "krunai"),
		ModelBaseURL: getenv("GROKBOT_MODEL_BASE_URL", "https://api.openai.com"),
		ModelAPIKey:  os.Getenv("GROKBOT_MODEL_API_KEY"),
		DefaultModel: getenv("GROKBOT_MODEL_DEFAULT", "gpt-5.6"),
	}
	if cfg.Token == "" {
		return Config{}, errors.New("GROKBOT_GATEWAY_TOKEN must be set")
	}
	if err := os.MkdirAll(cfg.StateDir, 0o700); err != nil {
		return Config{}, err
	}
	return cfg, nil
}

func newServer(cfg Config, store *Store) *Server {
	s := &Server{cfg: cfg, store: store, mux: http.NewServeMux()}
	s.mux.HandleFunc("GET /healthz", s.healthz)
	s.mux.HandleFunc("POST /v1/runtimes", s.createRuntime)
	s.mux.HandleFunc("POST /v1/runtimes/{id}/start", s.startRuntime)
	s.mux.HandleFunc("POST /v1/runtimes/{id}/stop", s.stopRuntime)
	s.mux.HandleFunc("DELETE /v1/runtimes/{id}", s.deleteRuntime)
	s.mux.HandleFunc("GET /v1/runtimes/{id}/health", s.runtimeHealth)
	s.mux.HandleFunc("POST /v1/runtimes/{id}/turns", s.runTurn)
	return s
}

func (s *Server) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/healthz" {
		if !constantBearer(r.Header.Get("Authorization"), s.cfg.Token) {
			writeError(w, http.StatusUnauthorized, "invalid gateway credential")
			return
		}
	}
	w.Header().Set("Content-Type", "application/json")
	s.mux.ServeHTTP(w, r)
}

func (s *Server) healthz(w http.ResponseWriter, _ *http.Request) {
	_, err := exec.LookPath(s.cfg.KrunaiBin)
	writeJSON(w, http.StatusOK, map[string]any{"ok": err == nil, "driver": "krunai", "krunai_found": err == nil})
}

func (s *Server) createRuntime(w http.ResponseWriter, r *http.Request) {
	var req createRuntimeRequest
	if err := decodeJSON(r, &req); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}
	if req.BotID == "" || req.IdempotencyKey == "" || req.VCPU < 1 || req.VCPU > 32 || req.MemoryMB < 256 || req.MemoryMB > 131072 {
		writeError(w, http.StatusUnprocessableEntity, "invalid runtime profile")
		return
	}
	if existing := s.store.byIdempotency(req.IdempotencyKey); existing != nil {
		writeJSON(w, http.StatusOK, existing)
		return
	}
	if _, err := exec.LookPath(s.cfg.KrunaiBin); err != nil {
		writeError(w, http.StatusServiceUnavailable, "krunai is not installed or not in PATH")
		return
	}

	externalID := "krunai-" + randomHex(12)
	vmName := sanitizeVMName("bot-" + req.BotID)
	workDir := filepath.Join(s.cfg.StateDir, "runtimes", externalID, "work")
	if err := os.MkdirAll(workDir, 0o700); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	if err := installRuntimeFiles(workDir); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	setup := filepath.Join(workDir, "bootstrap.sh")
	args := []string{"create", "--cpus", strconv.Itoa(req.VCPU), "--mem", strconv.Itoa(req.MemoryMB), vmName, setup}
	cmd := exec.Command(s.cfg.KrunaiBin, args...)
	cmd.Dir = workDir
	out, err := cmd.CombinedOutput()
	if err != nil {
		writeError(w, http.StatusBadGateway, fmt.Sprintf("krunai create failed: %s", truncate(string(out), 4000)))
		return
	}

	now := time.Now().UTC()
	rt := &Runtime{
		ExternalID: externalID, IdempotencyKey: req.IdempotencyKey, BotID: req.BotID, VMName: vmName,
		WorkDir: workDir, State: "running", VCPU: req.VCPU, MemoryMB: req.MemoryMB, DiskGB: 100,
		Image: "debian-13-krunai", Endpoint: "krunai://" + vmName, Generation: 1, NetworkPolicy: req.NetworkPolicy,
		CreatedAt: now, UpdatedAt: now,
	}
	if err := s.store.put(rt); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	writeJSON(w, http.StatusCreated, rt)
}

func (s *Server) startRuntime(w http.ResponseWriter, r *http.Request) {
	rt := s.store.get(r.PathValue("id"))
	if rt == nil {
		writeError(w, http.StatusNotFound, "runtime not found")
		return
	}
	if rt.State != "running" {
		if out, err := exec.Command(s.cfg.KrunaiBin, "start", rt.VMName).CombinedOutput(); err != nil {
			writeError(w, http.StatusBadGateway, "krunai start failed: "+truncate(string(out), 4000))
			return
		}
		rt.State = "running"
		rt.UpdatedAt = time.Now().UTC()
		_ = s.store.put(rt)
	}
	writeJSON(w, http.StatusOK, rt)
}

func (s *Server) stopRuntime(w http.ResponseWriter, r *http.Request) {
	rt := s.store.get(r.PathValue("id"))
	if rt == nil {
		writeError(w, http.StatusNotFound, "runtime not found")
		return
	}
	if rt.State == "running" {
		if out, err := exec.Command(s.cfg.KrunaiBin, "stop", rt.VMName).CombinedOutput(); err != nil {
			writeError(w, http.StatusBadGateway, "krunai stop failed: "+truncate(string(out), 4000))
			return
		}
		rt.State = "stopped"
		rt.UpdatedAt = time.Now().UTC()
		_ = s.store.put(rt)
	}
	writeJSON(w, http.StatusOK, rt)
}

func (s *Server) deleteRuntime(w http.ResponseWriter, r *http.Request) {
	rt := s.store.get(r.PathValue("id"))
	if rt == nil {
		writeError(w, http.StatusNotFound, "runtime not found")
		return
	}
	if rt.State == "running" {
		_, _ = exec.Command(s.cfg.KrunaiBin, "stop", rt.VMName).CombinedOutput()
	}
	out, err := exec.Command(s.cfg.KrunaiBin, "delete", "--yes", rt.VMName).CombinedOutput()
	if err != nil {
		writeError(w, http.StatusBadGateway, "krunai delete failed: "+truncate(string(out), 4000))
		return
	}
	if err := s.store.remove(rt.ExternalID); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	_ = os.RemoveAll(filepath.Dir(rt.WorkDir))
	writeJSON(w, http.StatusOK, map[string]any{"deleted": true})
}

func (s *Server) runtimeHealth(w http.ResponseWriter, r *http.Request) {
	rt := s.store.get(r.PathValue("id"))
	if rt == nil {
		writeError(w, http.StatusNotFound, "runtime not found")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"state": rt.State, "cpu_percent": 0.0, "memory_bytes": 0, "disk_bytes": 0,
		"observed_at": time.Now().UTC().Format(time.RFC3339Nano),
	})
}

func (s *Server) runTurn(w http.ResponseWriter, r *http.Request) {
	rt := s.store.get(r.PathValue("id"))
	if rt == nil {
		writeError(w, http.StatusNotFound, "runtime not found")
		return
	}
	if rt.State != "running" {
		writeError(w, http.StatusConflict, "runtime is not running")
		return
	}
	var req turnRequest
	if err := decodeJSON(r, &req); err != nil {
		writeError(w, http.StatusBadRequest, err.Error())
		return
	}
	if req.BotID != rt.BotID || req.OutputMessageID == "" || req.Callbacks.Chunks == "" || req.Callbacks.Complete == "" || strings.TrimSpace(req.Input) == "" {
		writeError(w, http.StatusUnprocessableEntity, "turn does not match runtime identity")
		return
	}
	if s.cfg.ModelAPIKey == "" {
		writeError(w, http.StatusServiceUnavailable, "GROKBOT_MODEL_API_KEY is not configured")
		return
	}
	if req.NetworkPolicy == nil {
		req.NetworkPolicy = rt.NetworkPolicy
	}

	payload := map[string]any{
		"bot_id": req.BotID, "conversation_id": req.ConversationID, "input": req.Input,
		"output_message_id": req.OutputMessageID, "model": firstNonEmpty(req.Model, s.cfg.DefaultModel),
		"system_prompt": req.SystemPrompt, "tools": req.Tools, "memory_enabled": req.MemoryEnabled,
		"network_policy": req.NetworkPolicy,
		"_provider": map[string]string{"base_url": s.cfg.ModelBaseURL, "api_key": s.cfg.ModelAPIKey, "model": s.cfg.DefaultModel},
	}
	turnDir := filepath.Join(rt.WorkDir, "turns")
	if err := os.MkdirAll(turnDir, 0o700); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}
	filename := "turn-" + randomHex(10) + ".json"
	path := filepath.Join(turnDir, filename)
	encoded, _ := json.Marshal(payload)
	if err := os.WriteFile(path, encoded, 0o600); err != nil {
		writeError(w, http.StatusInternalServerError, err.Error())
		return
	}

	go s.executeTurn(rt, req, filename)
	writeJSON(w, http.StatusAccepted, map[string]any{"accepted": true, "runtime_id": rt.ExternalID})
}

func (s *Server) executeTurn(rt *Runtime, turn turnRequest, filename string) {
	defer os.Remove(filepath.Join(rt.WorkDir, "turns", filename))
	guestPath := "/home/agent/work/turns/" + filename
	cmd := exec.Command(s.cfg.KrunaiBin, "connect", rt.VMName, "python3", "/home/agent/work/runtime/grokbot-runtime.py", guestPath)
	stdout, err := cmd.StdoutPipe()
	if err != nil {
		s.postFailure(turn.Callbacks.Complete, err)
		return
	}
	stderr := new(bytes.Buffer)
	cmd.Stderr = stderr
	if err := cmd.Start(); err != nil {
		s.postFailure(turn.Callbacks.Complete, err)
		return
	}

	completed := false
	scanner := bufio.NewScanner(stdout)
	scanner.Buffer(make([]byte, 64*1024), 2<<20)
	for scanner.Scan() {
		var event runtimeEvent
		if err := json.Unmarshal(scanner.Bytes(), &event); err != nil {
			continue
		}
		switch event.Type {
		case "chunk":
			body := map[string]any{"sequence": event.Sequence, "kind": firstNonEmpty(event.Kind, "text"), "delta": event.Delta}
			if err := s.postCallback(turn.Callbacks.Chunks, body); err != nil {
				log.Printf("chunk callback failed runtime=%s: %v", rt.ExternalID, err)
			}
		case "complete":
			body := map[string]any{"status": "complete", "token_count": event.TokenCount, "metadata": event.Metadata}
			if err := s.postCallback(turn.Callbacks.Complete, body); err != nil {
				log.Printf("complete callback failed runtime=%s: %v", rt.ExternalID, err)
			} else {
				completed = true
			}
		case "activity":
			log.Printf("runtime=%s event=%s tool=%s error=%s", rt.ExternalID, event.Event, event.Tool, event.Error)
		case "error":
			s.postFailure(turn.Callbacks.Complete, errors.New(event.Error))
		}
	}
	waitErr := cmd.Wait()
	if !completed && waitErr != nil {
		s.postFailure(turn.Callbacks.Complete, fmt.Errorf("runtime exited: %v: %s", waitErr, truncate(stderr.String(), 4000)))
	}
}

func (s *Server) postFailure(url string, err error) {
	_ = s.postCallback(url, map[string]any{"status": "failed", "metadata": map[string]any{"error": truncate(err.Error(), 4000)}})
}

func (s *Server) postCallback(url string, body any) error {
	encoded, err := json.Marshal(body)
	if err != nil {
		return err
	}
	req, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(encoded))
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+s.cfg.Token)
	req.Header.Set("Content-Type", "application/json")
	client := &http.Client{Timeout: 15 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		data, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
		return fmt.Errorf("callback status %d: %s", resp.StatusCode, string(data))
	}
	return nil
}

func loadStore(path string) (*Store, error) {
	if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
		return nil, err
	}
	s := &Store{path: path, Runtimes: map[string]*Runtime{}}
	data, err := os.ReadFile(path)
	if errors.Is(err, os.ErrNotExist) {
		return s, nil
	}
	if err != nil {
		return nil, err
	}
	if err := json.Unmarshal(data, s); err != nil {
		return nil, err
	}
	return s, nil
}

func (s *Store) get(id string) *Runtime {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if rt, ok := s.Runtimes[id]; ok {
		clone := *rt
		return &clone
	}
	return nil
}

func (s *Store) byIdempotency(key string) *Runtime {
	s.mu.RLock()
	defer s.mu.RUnlock()
	for _, rt := range s.Runtimes {
		if rt.IdempotencyKey == key {
			clone := *rt
			return &clone
		}
	}
	return nil
}

func (s *Store) put(rt *Runtime) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	clone := *rt
	s.Runtimes[rt.ExternalID] = &clone
	return s.saveLocked()
}

func (s *Store) remove(id string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	delete(s.Runtimes, id)
	return s.saveLocked()
}

func (s *Store) saveLocked() error {
	data, err := json.MarshalIndent(s, "", "  ")
	if err != nil {
		return err
	}
	tmp := s.path + ".tmp"
	if err := os.WriteFile(tmp, data, 0o600); err != nil {
		return err
	}
	return os.Rename(tmp, s.path)
}

func installRuntimeFiles(workDir string) error {
	runtimeDir := filepath.Join(workDir, "runtime")
	if err := os.MkdirAll(runtimeDir, 0o700); err != nil {
		return err
	}
	sourceCandidates := []string{
		filepath.Join("runtime", "grokbot-runtime.py"),
		filepath.Join(filepath.Dir(os.Args[0]), "runtime", "grokbot-runtime.py"),
	}
	var source string
	for _, candidate := range sourceCandidates {
		if _, err := os.Stat(candidate); err == nil {
			source = candidate
			break
		}
	}
	if source == "" {
		return errors.New("runtime/grokbot-runtime.py not found; run gateway from services/tinyvm-gateway")
	}
	data, err := os.ReadFile(source)
	if err != nil {
		return err
	}
	if err := os.WriteFile(filepath.Join(runtimeDir, "grokbot-runtime.py"), data, 0o700); err != nil {
		return err
	}
	bootstrap := "#!/bin/bash\nset -euo pipefail\nmkdir -p /home/agent/work/runtime /home/agent/work/turns\nchmod 700 /home/agent/work/runtime/grokbot-runtime.py\ncommand -v python3 >/dev/null 2>&1 || sudo apt-get update && sudo apt-get install -y python3 ca-certificates git\n"
	return os.WriteFile(filepath.Join(workDir, "bootstrap.sh"), []byte(bootstrap), 0o700)
}

func decodeJSON(r *http.Request, target any) error {
	defer r.Body.Close()
	decoder := json.NewDecoder(io.LimitReader(r.Body, maxRequestBody+1))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(target); err != nil {
		return fmt.Errorf("invalid JSON: %w", err)
	}
	return nil
}

func constantBearer(header, token string) bool {
	const prefix = "Bearer "
	if token == "" || !strings.HasPrefix(header, prefix) {
		return false
	}
	provided := strings.TrimPrefix(header, prefix)
	if len(provided) != len(token) {
		return false
	}
	var diff byte
	for i := 0; i < len(token); i++ {
		diff |= provided[i] ^ token[i]
	}
	return diff == 0
}

func randomHex(n int) string {
	buf := make([]byte, n)
	if _, err := rand.Read(buf); err != nil {
		panic(err)
	}
	return hex.EncodeToString(buf)
}

func sanitizeVMName(value string) string {
	value = strings.ToLower(value)
	var out strings.Builder
	for _, r := range value {
		if (r >= 'a' && r <= 'z') || (r >= '0' && r <= '9') || r == '-' {
			out.WriteRune(r)
		}
	}
	name := strings.Trim(out.String(), "-")
	if len(name) > 52 {
		name = name[:52]
	}
	return name + "-" + randomHex(3)
}

func writeJSON(w http.ResponseWriter, status int, value any) {
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(value)
}

func writeError(w http.ResponseWriter, status int, message string) {
	writeJSON(w, status, map[string]any{"error": message})
}

func truncate(value string, max int) string {
	if len(value) <= max {
		return value
	}
	return value[:max] + "…"
}

func getenv(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if value != "" {
			return value
		}
	}
	return ""
}
