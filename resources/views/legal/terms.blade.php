@extends('legal.layout')

@section('title', 'Terms of use')

@section('content')
    <p>
        By using {{ config('app.name') }} you agree to these terms. If you do not agree, please do not use the app.
    </p>

    <h2>What the app does</h2>
    <p>
        {{ config('app.name') }} connects passengers with independent tricycle drivers in Pagadian City. We do not own or drive
        the tricycles and the drivers are not our employees. The trip is between the passenger and the driver.
        The app does not collect payments: any fare is settled between the passenger and the driver.
    </p>

    <h2>Your account</h2>
    <ul>
        <li>Give your real email address and mobile number, and keep your sign-in codes to yourself.</li>
        <li>You are responsible for what happens in your account.</li>
        <li>You can delete your account at any time. See <a href="{{ route('legal.account-deletion') }}">how to delete your account</a>.</li>
    </ul>

    <h2>Drivers</h2>
    <ul>
        <li>Give true and current documents. A driver can go online only after an admin approves the application.</li>
        <li>Follow the traffic laws and the rules of the city for tricycles.</li>
        <li>We may remove a driver who gives false documents or is the subject of serious reports.</li>
    </ul>

    <h2>How to behave</h2>
    <ul>
        <li>Be respectful in the trip and in the chat.</li>
        <li>Do not use the app to harass, threaten, scam or put anybody in danger, and do not send illegal or abusive content.</li>
        <li>Do not misuse the emergency button. It texts your emergency contacts and is meant for real emergencies. In an emergency, also call 911.</li>
    </ul>

    <h2>Limits</h2>
    <p>
        We try to keep the app working, but we cannot promise that it is always available or free of errors, or that a driver will
        be near you. To the extent the law allows, {{ config('legal.operator') }} is not responsible for what happens during a
        trip, such as accidents, delays or disputes between a passenger and a driver.
    </p>

    <h2>Privacy</h2>
    <p>How we handle your data is explained in the <a href="{{ route('legal.privacy') }}">privacy policy</a>.</p>

    <h2>Changes</h2>
    <p>We may change these terms. The new version is put on this page with a new date. If you keep using the app, you accept it.</p>
@endsection
