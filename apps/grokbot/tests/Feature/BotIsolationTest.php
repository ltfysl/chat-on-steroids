<?php

use App\Domain\Bots\DeployBot;

it('provisions a different runtime identity for every bot', function () {
    $alpha = isolatedBotFixture('Alpha');
    $beta = isolatedBotFixture('Beta');
    $deploy = app(DeployBot::class);

    $alphaRuntime = $deploy->handle($alpha);
    $betaRuntime = $deploy->handle($beta);

    expect($alphaRuntime->bot_id)->toBe($alpha->id)
        ->and($betaRuntime->bot_id)->toBe($beta->id)
        ->and($alphaRuntime->external_id)->not->toBe($betaRuntime->external_id);
});

it('makes deploy idempotent for the same bot', function () {
    $bot = isolatedBotFixture('Idempotent');
    $deploy = app(DeployBot::class);

    $first = $deploy->handle($bot);
    $second = $deploy->handle($bot->fresh());

    expect($second->id)->toBe($first->id)
        ->and($bot->runtime()->count())->toBe(1)
        ->and($second->external_id)->toBe($first->external_id);
});
