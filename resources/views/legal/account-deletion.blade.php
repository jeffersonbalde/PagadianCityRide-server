@extends('legal.layout')

@section('title', 'Delete your account')

@section('content')
    <p>You can delete your {{ config('app.name') }} account and the data in it at any time.</p>

    <h2>In the app</h2>
    <ol>
        <li>Open the app and sign in.</li>
        <li>Go to <strong>Account</strong>.</li>
        <li>Tap <strong>Delete account</strong> and confirm.</li>
    </ol>
    <p>You must finish a trip that is in progress first.</p>

    <h2>What is erased</h2>
    <ul>
        <li><strong>Passengers:</strong> your name, email address, mobile number, emergency contacts, trips, chat messages and photos, and your notifications.</li>
        <li><strong>Drivers:</strong> your name, email address, mobile number, tricycle details, documents (photo, license, plate papers, franchise) and reviews. A trip you drove stays for the passenger, without your details.</li>
    </ul>
    <p>This cannot be undone.</p>

    <h2>If you cannot open the app</h2>
    <p>
        @if (config('legal.contact_email'))
            Email <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>
        @else
            Write to us through the Google Play page of the app
        @endif
        from the email address of the account and ask us to delete it. We will answer you and delete the account.
    </p>
@endsection
