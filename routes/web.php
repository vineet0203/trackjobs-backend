<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'TrakJobs API is running 🚀'
    ]);
    //return view('welcome');
});

Route::get('/test-mail', function () {
    Mail::raw('This is a test email from TrackJobs.', function ($message) {
        $message->to('trackjobsofficial@gmail.com')
                ->subject('TrackJobs SMTP Test');
    });
    return 'Mail Sent Successfully';
});
