<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

trait DetectAndUpdateIfChanged
{
    /**
     * Fill model and update only if changed.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @param string $resourceName
     * @return \Illuminate\Http\JsonResponse|null
     */
   public function updateIfChanged(Model $model, array $data, string $resourceName = 'Resource'): ?JsonResponse
{
    // Lấy các casts từ model
    $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];

    // Map data theo kiểu cast của model
    foreach ($data as $key => &$value) {
        if (!array_key_exists($key, $casts)) {
            continue;
        }

        switch ($casts[$key]) {
            case 'integer':
            case 'int':
                $value = (int) $value;
                break;
            case 'float':
            case 'double':
                $value = (float) $value;
                break;
            case 'boolean':
            case 'bool':
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                break;
            case 'array':
                $value = is_array($value) ? $value : json_decode($value, true);
                break;
            case 'json':
                $value = is_array($value) ? $value : json_decode($value, true);
                break;
            case 'date':
            case 'datetime':
                $value = \Carbon\Carbon::parse($value);
                break;
            case 'string':
                $value = (string) $value;
                break;
        }
    }

    $model->fill($data);
    if (!$model->isDirty()) {
        return response()->json([
            'message' => "No changes detected in $resourceName.",
            'data' => $model
        ]);
    }

    $model->save();

    return response()->json([
        'message' => "$resourceName updated successfully.",
        'data' => $model
    ]);
}

}
