<?php

declare(strict_types=1);

namespace App\Support\Concerns;

/**
 * The load / error / retry plumbing every wired native screen shares.
 *
 * A screen sets \`$error\` from its \`load()\` and its blade swaps the body for the
 * retry card; \`retry()\` re-runs the same load.
 */
trait LoadsFromApi {
    public ?string $error = null;

    /** A failed mutation: shown inline so the form keeps what was typed. */
    public ?string $actionError = null;

    public function retry(): void {
        $this->error = null;
        $this->actionError = null;
        $this->load();
    }

    abstract protected function load(): void;
}
