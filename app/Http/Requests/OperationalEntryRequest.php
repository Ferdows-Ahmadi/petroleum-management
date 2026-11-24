<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class OperationalEntryRequest extends FormRequest
{
    protected function validationRules(): array
    {
        return parent::validationRules() + [
            'submit_action' => ['prohibited'],
            'status' => ['prohibited'],
            'save_draft' => ['prohibited'],
        ];
    }
}
