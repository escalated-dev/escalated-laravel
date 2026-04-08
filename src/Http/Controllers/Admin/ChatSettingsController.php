<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChatSettingsController extends Controller
{
    /**
     * Get current chat settings.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'chat_enabled' => EscalatedSettings::getBool('chat_enabled', false),
            'chat_sound_enabled' => EscalatedSettings::getBool('chat_sound_enabled', true),
            'chat_pre_chat_form_fields' => json_decode(EscalatedSettings::get('chat_pre_chat_form_fields', '[]'), true),
            'chat_widget_theme' => json_decode(EscalatedSettings::get('chat_widget_theme', '{}'), true),
        ]);
    }

    /**
     * Update chat settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_enabled' => ['sometimes', 'boolean'],
            'chat_sound_enabled' => ['sometimes', 'boolean'],
            'chat_pre_chat_form_fields' => ['sometimes', 'array'],
            'chat_widget_theme' => ['sometimes', 'array'],
        ]);

        foreach ($validated as $key => $value) {
            if (is_array($value)) {
                EscalatedSettings::set($key, json_encode($value));
            } elseif (is_bool($value)) {
                EscalatedSettings::set($key, $value ? '1' : '0');
            } else {
                EscalatedSettings::set($key, $value);
            }
        }

        return response()->json(['message' => 'Chat settings updated.']);
    }
}
