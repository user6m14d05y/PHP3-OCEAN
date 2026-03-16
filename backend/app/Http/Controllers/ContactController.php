<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // 
    }
    public function SubmitContact(Request $request)
    {
        $email = $request->input('email');

        // Check email validate
        $checkEmail = \Illuminate\Support\Facades\DB::select("SELECT * FROM contact WHERE email = ?", [$email]);

        if (count($checkEmail) > 0) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'email' => ['Địa chỉ email này đã được sử dụng!']
                ]
            ], 422); 
        }

        // Insert data to sql
        \Illuminate\Support\Facades\DB::insert( "INSERT INTO contact (email) VALUES (?)", [$email] );

        // Report success to interface
        return response()->json([
            'status' => 'success',
            'message' => 'Bạn đã điền email chúng tôi sẽ sớm liên hệ với bạn sớm nhất!'
        ], 201); 
    }
    public function show(Contact $contact)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact)
    {
        //
    }
    public function destroy(Contact $contact)
    {
        //
    }
}
