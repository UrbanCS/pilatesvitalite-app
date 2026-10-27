<?php

use Illuminate\Support\Facades\Route;
use Modules\NewsLetter\app\Http\Controllers\Admin\NewsLetterController as AdminNewsLetterController;
use Modules\NewsLetter\app\Http\Controllers\Admin\MailingListController as AdminMailingListController;
use Modules\NewsLetter\app\Http\Controllers\NewsLetterController;
use Modules\NewsLetter\app\Http\Controllers\Admin\CampaignController;
use Modules\NewsLetter\app\Http\Controllers\Admin\NewsletterTemplateController;

Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => ['auth:admin', 'translation']], function () {
    Route::get('subscriber-list', [AdminNewsLetterController::class, 'index'])->name('subscriber-list');
    Route::delete('subscriber-delete/{id}', [AdminNewsLetterController::class, 'destroy'])->name('subscriber-delete');
    Route::get('send-mail-to-newsletter', [AdminNewsLetterController::class, 'create'])->name('send-mail-to-newsletter');
    Route::post('send-mail-to-subscriber', [AdminNewsLetterController::class, 'store'])->name('send-mail-to-subscriber');

    Route::controller(AdminMailingListController::class)->prefix('mailing-lists')->group(function () {
        Route::get('/', 'index')->name('mailing-list-index');
        Route::get('create', 'create')->name('mailing-list-create');
        Route::post('store', 'store')->name('mailing-list-store');
        Route::get('{list}/edit', 'edit')->name('mailing-list-edit');
        Route::put('{list}/update', 'update')->name('mailing-list-update');
        Route::delete('{list}/delete', 'destroy')->name('mailing-list-delete');
        Route::get('{list}/manage', 'manageSubscribers')->name('mailing-list-manage');
        Route::post('{list}/add-subscribers', 'addSubscribers')->name('mailing-list-add-subscriber');
        Route::post('{list}/import-members', 'importMembers')->name('mailing-list-import-members');
        Route::delete('{list}/remove-subscriber', 'removeSubscriber')->name('mailing-list-remove-subscriber');
    });

    Route::get('newsletter-campaigns', [CampaignController::class, 'index'])->name('newsletter-campaigns.index');
    Route::get('newsletter-campaigns/{campaign}', [CampaignController::class, 'show'])->name('newsletter-campaigns.show');
    Route::delete('newsletter-campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('newsletter-campaigns.destroy');

    Route::controller(NewsletterTemplateController::class)->prefix('newsletter-templates')->name('newsletter-templates.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('store', 'store')->name('store');
        Route::get('{template}/edit', 'edit')->name('edit');
        Route::put('{template}/update', 'update')->name('update');
        Route::delete('{template}/delete', 'destroy')->name('destroy');
        Route::get('{template}/json', 'getTemplateJson')->name('json');
    });
});

Route::post('newsletter-request', [NewsLetterController::class, 'newsletter_request'])->name('newsletter-request');
Route::get('newsletter-verification/{token}', [NewsLetterController::class, 'newsletter_verification'])->name('newsletter-verification');
Route::get('newsletter-unsubscribe/{token}', [NewsLetterController::class, 'newsletter_unsubscribe'])->name('newsletter-unsubscribe');
