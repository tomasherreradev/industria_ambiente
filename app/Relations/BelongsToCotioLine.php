<?php

namespace App\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relación CotioInstancia → fila Cotio por (cotio_numcoti, cotio_item, cotio_subitem).
 *
 * No usar belongsTo con arrays de claves hacia {@see \App\Models\Cotio}: su PK compuesta
 * hace que Laravel llame a qualifyColumn() con array y falle (str_contains).
 */
class BelongsToCotioLine extends BelongsTo
{
    public function __construct(
        Builder $query,
        Model $child,
        string $foreignKey,
        string $ownerKey,
        string $relationName,
        protected bool $linkToMuestraRow = false,
    ) {
        parent::__construct($query, $child, $foreignKey, $ownerKey, $relationName);
    }

    public function addConstraints()
    {
        if (static::$constraints) {
            $table = $this->related->getTable();
            $this->query->where($table.'.cotio_numcoti', '=', $this->child->getAttribute('cotio_numcoti'));
            $this->applyLineConstraints($this->child);
        }
    }

    protected function applyLineConstraints(Model $child): void
    {
        $table = $this->related->getTable();
        $this->query->where($table.'.cotio_item', '=', $child->getAttribute('cotio_item'));

        $subitem = $this->linkToMuestraRow ? 0 : (int) $child->getAttribute('cotio_subitem');
        $this->query->where($table.'.cotio_subitem', '=', $subitem);
    }

    public function addEagerConstraints(array $models): void
    {
        if ($models === []) {
            $this->query->whereRaw('0 = 1');

            return;
        }

        $table = $this->related->getTable();

        $this->query->where(function ($query) use ($models, $table) {
            $seen = [];
            foreach ($models as $model) {
                $subitem = $this->linkToMuestraRow ? 0 : (int) $model->getAttribute('cotio_subitem');
                $k = $model->getAttribute('cotio_numcoti').'|'.$model->getAttribute('cotio_item').'|'.$subitem;
                if (isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;

                $query->orWhere(function ($q) use ($model, $table, $subitem) {
                    $q->where($table.'.cotio_numcoti', '=', $model->getAttribute('cotio_numcoti'))
                        ->where($table.'.cotio_item', '=', $model->getAttribute('cotio_item'))
                        ->where($table.'.cotio_subitem', '=', $subitem);
                });
            }
        });
    }

    protected function getForeignKeyFrom(Model $model): mixed
    {
        $subitem = $this->linkToMuestraRow ? 0 : (int) $model->getAttribute('cotio_subitem');

        return $model->getAttribute('cotio_numcoti').'|'.$model->getAttribute('cotio_item').'|'.$subitem;
    }

    protected function getRelatedKeyFrom(Model $model): mixed
    {
        return $model->getAttribute('cotio_numcoti').'|'.$model->getAttribute('cotio_item').'|'.$model->getAttribute('cotio_subitem');
    }
}
