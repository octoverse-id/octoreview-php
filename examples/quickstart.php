<?php

/*
 * Creates a review with a photo and prints its id, the entity's rating summary and the photo URL
 * (server docs/spec.md section 18).
 *
 * Run the server's compose stack and copy its demo settings out first:
 *
 *     make up && make dev-env                            # in the octoreview server repository
 *     php examples/quickstart.php path/to/.octoreview-dev.env
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Octoverse\Octoreview\Client;
use Octoverse\Octoreview\ClientOptions;

$envFile = $argv[1] ?? '.octoreview-dev.env';
$lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    fwrite(STDERR, "quickstart: cannot read {$envFile} (run `make dev-env` in the server repository)\n");
    exit(1);
}
// KEY=VALUE lines; a variable already set in the environment wins.
$env = [];
foreach ($lines as $line) {
    $pair = explode('=', trim($line), 2);
    if (count($pair) === 2 && !str_starts_with($pair[0], '#')) {
        $set = getenv($pair[0]);
        $env[$pair[0]] = $set === false ? $pair[1] : $set;
    }
}
$client = Client::fromEnv(new ClientOptions(), $env);

$review = $client->reviews()->create(
    // In your application, derive this from your own authenticated user.
    'quickstart-php-' . bin2hex(random_bytes(6)),
    5,
    [
        'entity_type' => 'product',
        'external_id' => 'sku-quickstart',
        'title' => 'Fits perfectly',
        'body' => 'Sent with the octoreview PHP quickstart.',
    ],
);
$media = $client->media()->uploadFile($review->id, __DIR__ . '/photo.png');
$summary = $client->ratings()->get('product', 'sku-quickstart');

printf("review: %s\n", $review->id);
printf(
    "rating: average %s from %d published review(s)\n",
    $summary->ratingAverage === null ? 'none' : number_format($summary->ratingAverage, 2),
    $summary->ratingCount,
);
printf("photo: %s\n", $media->url ?? '(no URL)');
