<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class PageController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('welcome', [
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }

    public function dashboard(): Response
    {
        return Inertia::render('dashboard');
    }

    public function editProfileRedirect(): RedirectResponse
    {
        return to_route('profile.edit');
    }

    public function settingNotifikasi(): Response
    {
        return Inertia::render('setting-notifikasi');
    }

    public function tugasAkhir(): Response
    {
        return Inertia::render('tugas-akhir');
    }

    public function jadwalBimbingan(): Response
    {
        return Inertia::render('jadwal-bimbingan');
    }

    public function jadwalBimbinganCreateRedirect(): RedirectResponse
    {
        return to_route('jadwal-bimbingan', ['open' => 'ajukan']);
    }

    public function uploadDokumen(): Response
    {
        return Inertia::render('upload-dokumen');
    }

    public function uploadDokumenCreateRedirect(): RedirectResponse
    {
        return to_route('upload-dokumen', ['open' => 'unggah']);
    }

    public function pesan(): Response
    {
        return Inertia::render('pesan');
    }

    public function panduan(): Response
    {
        return Inertia::render('panduan');
    }

    public function appearance(): Response
    {
        return Inertia::render('settings/appearance');
    }
}
