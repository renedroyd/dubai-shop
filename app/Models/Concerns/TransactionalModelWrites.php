<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

trait TransactionalModelWrites
{
    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }
}
