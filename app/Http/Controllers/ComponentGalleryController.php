<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class ComponentGalleryController extends Controller
{
    public function index(): View
    {
        return view('pages.component-gallery', [
            'locale' => app()->getLocale(),
            'components' => [
                'button', 'card', 'modal', 'input', 'select',
                'table', 'badge', 'language-switcher',
            ],
        ]);
    }
}
