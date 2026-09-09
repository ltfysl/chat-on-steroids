#!/usr/bin/env python3
import json
import os
import pathlib
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path('/home/agent/work').resolve()
MAX_FILE = 1_000_000
MAX_HTTP = 1_000_000
MAX_TOOL_STEPS = 12


def emit(kind, **payload):
    print(json.dumps({'type': kind, **payload}, separators=(',', ':')), flush=True)


def inside_root(raw):
    path = (ROOT / raw).resolve()
    if path != ROOT and ROOT not in path.parents:
        raise ValueError('path escapes bot workspace')
    return path


def terminal_exec(args):
    argv = args.get('argv')
    if not isinstance(argv, list) or not argv or not all(isinstance(v, str) for v in argv):
        raise ValueError('argv must be a non-empty string array')
    cwd = inside_root(args.get('cwd', '.'))
    result = subprocess.run(argv, cwd=cwd, capture_output=True, text=True, timeout=min(int(args.get('timeout', 60)), 120), env={**os.environ, 'CI': '1'})
    return {'exit_code': result.returncode, 'stdout': result.stdout[-200_000:], 'stderr': result.stderr[-100_000:]}


def read_file(args):
    path = inside_root(args['path'])
    data = path.read_bytes()
    if len(data) > MAX_FILE:
        raise ValueError('file exceeds 1 MB tool limit')
    return {'content': data.decode('utf-8', errors='replace')}


def write_file(args):
    content = str(args.get('content', '')).encode()
    if len(content) > MAX_FILE:
        raise ValueError('write exceeds 1 MB tool limit')
    path = inside_root(args['path'])
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(content)
    return {'bytes': len(content)}


def list_files(args):
    path = inside_root(args.get('path', '.'))
    entries = []
    for item in sorted(path.iterdir(), key=lambda p: p.name)[:500]:
        entries.append({'name': item.name, 'directory': item.is_dir(), 'size': item.stat().st_size if item.is_file() else None})
    return {'entries': entries}


def allowed_host(host, policy):
    mode = (policy or {}).get('mode', 'deny-by-default')
    allow = (policy or {}).get('allow', [])
    if mode == 'open':
        return True
    host = host.lower().rstrip('.')
    for rule in allow:
        rule = str(rule).lower().rstrip('.')
        if host == rule or (rule.startswith('*.') and host.endswith(rule[1:])):
            return True
    return False


def http_get(args, policy):
    url = args['url']
    parsed = urllib.parse.urlparse(url)
    if parsed.scheme not in ('http', 'https') or not parsed.hostname:
        raise ValueError('only http/https URLs are supported')
    if not allowed_host(parsed.hostname, policy):
        raise PermissionError(f'network policy denies {parsed.hostname}')
    request = urllib.request.Request(url, headers={'User-Agent': 'grokbot-runtime/1'})
    with urllib.request.urlopen(request, timeout=15) as response:
        data = response.read(MAX_HTTP + 1)
        if len(data) > MAX_HTTP:
            raise ValueError('HTTP response exceeds 1 MB tool limit')
        return {'status': response.status, 'content_type': response.headers.get('Content-Type'), 'body': data.decode('utf-8', errors='replace')}


def git_exec(args):
    argv = args.get('argv', [])
    if not isinstance(argv, list) or not all(isinstance(v, str) for v in argv):
        raise ValueError('argv must be an array')
    return terminal_exec({'argv': ['git', *argv], 'cwd': args.get('cwd', '.'), 'timeout': args.get('timeout', 60)})


def tool_specs(enabled):
    specs = []
    if 'terminal' in enabled:
        specs.append({'type': 'function', 'function': {'name': 'terminal_exec', 'description': 'Execute one program directly inside the isolated bot VM. No shell expansion.', 'parameters': {'type': 'object', 'properties': {'argv': {'type': 'array', 'items': {'type': 'string'}}, 'cwd': {'type': 'string'}, 'timeout': {'type': 'integer'}}, 'required': ['argv']}}})
    if 'files' in enabled:
        specs += [
            {'type': 'function', 'function': {'name': 'read_file', 'description': 'Read a UTF-8 file from the bot workspace.', 'parameters': {'type': 'object', 'properties': {'path': {'type': 'string'}}, 'required': ['path']}}},
            {'type': 'function', 'function': {'name': 'write_file', 'description': 'Write a UTF-8 file inside the bot workspace.', 'parameters': {'type': 'object', 'properties': {'path': {'type': 'string'}, 'content': {'type': 'string'}}, 'required': ['path', 'content']}}},
            {'type': 'function', 'function': {'name': 'list_files', 'description': 'List a directory inside the bot workspace.', 'parameters': {'type': 'object', 'properties': {'path': {'type': 'string'}}}}},
        ]
    if 'http' in enabled or 'browser' in enabled:
        specs.append({'type': 'function', 'function': {'name': 'http_get', 'description': 'Fetch an allowlisted HTTP(S) resource.', 'parameters': {'type': 'object', 'properties': {'url': {'type': 'string'}}, 'required': ['url']}}})
    if 'git' in enabled:
        specs.append({'type': 'function', 'function': {'name': 'git_exec', 'description': 'Run git inside the bot workspace.', 'parameters': {'type': 'object', 'properties': {'argv': {'type': 'array', 'items': {'type': 'string'}}, 'cwd': {'type': 'string'}}, 'required': ['argv']}}})
    return specs


def execute_tool(name, args, payload):
    handlers = {
        'terminal_exec': lambda: terminal_exec(args),
        'read_file': lambda: read_file(args),
        'write_file': lambda: write_file(args),
        'list_files': lambda: list_files(args),
        'http_get': lambda: http_get(args, payload.get('network_policy')),
        'git_exec': lambda: git_exec(args),
    }
    if name not in handlers:
        raise ValueError(f'unknown or disabled tool {name}')
    emit('activity', event='tool.started', tool=name)
    try:
        result = handlers[name]()
        emit('activity', event='tool.completed', tool=name)
        return result
    except Exception as exc:
        emit('activity', event='tool.failed', tool=name, error=str(exc))
        return {'error': str(exc)}


def request_chat(provider, body):
    base = provider['base_url'].rstrip('/')
    if not base.endswith('/v1'):
        base += '/v1'
    request = urllib.request.Request(
        base + '/chat/completions',
        data=json.dumps(body).encode(),
        headers={'Content-Type': 'application/json', 'Authorization': 'Bearer ' + provider['api_key']},
        method='POST',
    )
    try:
        with urllib.request.urlopen(request, timeout=120) as response:
            raw = response.read(10_000_000)
    except urllib.error.HTTPError as exc:
        detail = exc.read(100_000).decode('utf-8', errors='replace')
        raise RuntimeError(f'model provider HTTP {exc.code}: {detail}') from exc
    return json.loads(raw)


def main():
    if len(sys.argv) != 2:
        raise SystemExit('usage: grokbot-runtime.py payload.json')
    payload_path = inside_root(sys.argv[1].replace('/home/agent/work/', '', 1))
    payload = json.loads(payload_path.read_text())
    provider = payload.get('_provider') or {}
    if not provider.get('base_url') or not provider.get('api_key'):
        raise RuntimeError('model provider is not configured in tinyvm-gateway')

    messages = []
    if payload.get('system_prompt'):
        messages.append({'role': 'system', 'content': payload['system_prompt']})
    messages.append({'role': 'user', 'content': payload['input']})
    enabled = set(payload.get('tools') or [])
    tools = tool_specs(enabled)

    for _ in range(MAX_TOOL_STEPS):
        body = {'model': payload.get('model') or provider.get('model'), 'messages': messages, 'temperature': 0.2}
        if tools:
            body['tools'] = tools
            body['tool_choice'] = 'auto'
        response = request_chat(provider, body)
        message = response['choices'][0]['message']
        messages.append(message)
        calls = message.get('tool_calls') or []
        if calls:
            for call in calls:
                fn = call.get('function', {})
                try:
                    args = json.loads(fn.get('arguments') or '{}')
                except json.JSONDecodeError:
                    args = {}
                result = execute_tool(fn.get('name', ''), args, payload)
                messages.append({'role': 'tool', 'tool_call_id': call['id'], 'content': json.dumps(result, separators=(',', ':'))})
            continue

        content = message.get('content') or ''
        sequence = 1
        for offset in range(0, len(content), 96):
            emit('chunk', sequence=sequence, kind='text', delta=content[offset:offset + 96])
            sequence += 1
        usage = response.get('usage') or {}
        emit('complete', token_count=usage.get('total_tokens'), metadata={'provider_model': response.get('model')})
        payload_path.unlink(missing_ok=True)
        return

    raise RuntimeError('agent exceeded maximum tool steps')


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        emit('error', error=str(exc))
        sys.exit(1)
