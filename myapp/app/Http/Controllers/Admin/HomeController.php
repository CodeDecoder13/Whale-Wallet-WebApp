<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** The admin landing page; the overview numbers arrive in phase 2. */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/Home');
    }
}
