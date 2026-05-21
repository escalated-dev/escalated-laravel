<?php

use Escalated\Laravel\Http\Controllers\Admin\NewsletterController;
use Escalated\Laravel\Http\Controllers\Admin\NewsletterListController;
use Escalated\Laravel\Http\Controllers\Admin\NewsletterSettingsController;
use Escalated\Laravel\Http\Controllers\Admin\NewsletterTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [NewsletterController::class, 'index'])->name('index');
Route::get('/new', [NewsletterController::class, 'create'])->name('create');
Route::post('/', [NewsletterController::class, 'store'])->name('store');
Route::post('/preview', [NewsletterController::class, 'preview'])->name('preview');
Route::post('/test', [NewsletterController::class, 'testSend'])->name('testSend');

Route::get('/lists', [NewsletterListController::class, 'index'])->name('lists.index');
Route::get('/lists/new', [NewsletterListController::class, 'create'])->name('lists.create');
Route::post('/lists', [NewsletterListController::class, 'store'])->name('lists.store');
Route::get('/lists/{list}', [NewsletterListController::class, 'show'])->name('lists.show');
Route::put('/lists/{list}', [NewsletterListController::class, 'update'])->name('lists.update');
Route::delete('/lists/{list}', [NewsletterListController::class, 'destroy'])->name('lists.destroy');
Route::post('/lists/{list}/members', [NewsletterListController::class, 'addMember'])->name('lists.members.add');
Route::delete('/lists/{list}/members/{contactId}', [NewsletterListController::class, 'removeMember'])->name('lists.members.remove');
Route::post('/lists/{list}/import', [NewsletterListController::class, 'importCsv'])->name('lists.import');

Route::get('/templates', [NewsletterTemplateController::class, 'index'])->name('templates.index');
Route::get('/templates/new', [NewsletterTemplateController::class, 'create'])->name('templates.create');
Route::post('/templates', [NewsletterTemplateController::class, 'store'])->name('templates.store');
Route::get('/templates/{template}', [NewsletterTemplateController::class, 'show'])->name('templates.show');
Route::put('/templates/{template}', [NewsletterTemplateController::class, 'update'])->name('templates.update');
Route::delete('/templates/{template}', [NewsletterTemplateController::class, 'destroy'])->name('templates.destroy');

Route::get('/settings', [NewsletterSettingsController::class, 'show'])->name('settings.show');
Route::put('/settings', [NewsletterSettingsController::class, 'update'])->name('settings.update');

// Catch-all routes (must be last to avoid shadowing /new, /lists, /templates, /settings, /preview, /test)
Route::get('/{newsletter}', [NewsletterController::class, 'show'])->name('show');
Route::get('/{newsletter}/edit', [NewsletterController::class, 'edit'])->name('edit');
Route::put('/{newsletter}', [NewsletterController::class, 'update'])->name('update');
Route::delete('/{newsletter}', [NewsletterController::class, 'destroy'])->name('destroy');
