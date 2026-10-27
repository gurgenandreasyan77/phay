<?php

namespace App\DataTables\Duplicates;

/** Ids joined in pairs, read back as the groups they make (union-find). */
final class Links
{
    /** @var array<int, int> */
    private array $parent = [];

    public function join(int $a, int $b): void
    {
        $this->parent[$a] ??= $a;
        $this->parent[$b] ??= $b;
        $ra = $this->root($a);
        $rb = $this->root($b);
        if ($ra !== $rb) {
            $this->parent[max($ra, $rb)] = min($ra, $rb);
        }
    }

    /** @return list<list<int>> */
    public function groups(): array
    {
        $groups = [];
        foreach (array_keys($this->parent) as $id) {
            $groups[$this->root($id)][] = $id;
        }

        return array_values($groups);
    }

    private function root(int $id): int
    {
        while ($this->parent[$id] !== $id) {
            $this->parent[$id] = $this->parent[$this->parent[$id]];
            $id = $this->parent[$id];
        }

        return $id;
    }
}
