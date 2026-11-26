@extends('legal.layout')

@section('title', 'Privacy policy')

@section('content')
    <p>
        {{ config('app.name') }} is an app that connects passengers with tricycle drivers in Pagadian City. It is run by
        {{ config('legal.operator') }}. This page explains what we collect, why, who sees it, and how you can delete it. We follow
        the Data Privacy Act of 2012 (Republic Act 10173) of the Philippines.
    </p>

    <h2>What we collect</h2>
    <table>
        <tr><th>Who</th><th>What</th></tr>
        <tr><td>Everyone</td><td>Your email address (to sign in) and the code we email you, your mobile number (typed in so a driver and a passenger can call each other, and not checked at first), your name, the role you chose (passenger or driver), a token that lets us send notifications to your phone, and the code we text you to sign in.</td></tr>
        <tr><td>Passengers</td><td>The pick-up point and the destination of each trip, the trip status and times, the note you write for the driver, your rating and review, chat messages and photos you send in a trip, reports you make, and the emergency contacts you add (name and mobile number).</td></tr>
        <tr><td>Drivers</td><td>Your photo, driver's license, tricycle plate papers and franchise (as photos), your tricycle and plate number, and your location while you are online.</td></tr>
    </table>
    <p>We do not collect payments. We do not sell your data and we do not show ads.</p>

    <h2>Location</h2>
    <ul>
        <li>A passenger's location is used only while the app is open, to set the pick-up point and to find nearby drivers.</li>
        <li>A driver's location is shared with passengers only while the driver is online or on a trip.</li>
        <li>The app does not track you in the background.</li>
    </ul>

    <h2>Why we use it</h2>
    <ul>
        <li>To sign you in and keep your account safe.</li>
        <li>To match a passenger with a driver, show the trip on the map and let them chat.</li>
        <li>To let admins check driver applications and look into reports.</li>
        <li>To text your emergency contacts with your location when you press the emergency button.</li>
        <li>To send you notifications about your trip.</li>
    </ul>

    <h2>Who sees it</h2>
    <ul>
        <li>The passenger and the driver of a trip see each other's name, the trip details and the trip chat. The phone number of one is shown to the other only as needed for the trip.</li>
        <li>Your emergency contacts get a text only when you press the emergency button.</li>
        <li>Admins of {{ config('app.name') }} see account details, trips, driver applications, documents and reports so they can approve drivers, handle problems and keep the service safe. They read the chat of a trip only when that trip was reported. They can block or delete an account that breaks the terms.</li>
        <li>Nobody else sees your driver documents.</li>
    </ul>

    <h2>Companies that help us run the app</h2>
    <p>They handle data for us only to do their job:</p>
    <ul>
        <li><strong>DigitalOcean</strong> hosts our server, database and private file storage (in Singapore).</li>
        <li><strong>Google (Gmail)</strong> sends the emails: the sign-in code and the answer to a driver application. It receives the email address and the message.</li>
        <li><strong>Semaphore</strong> sends the text messages: the emergency alerts to your emergency contacts, and later also the proof of your mobile number. It receives the mobile number and the text.</li>
        <li><strong>Expo</strong> delivers push notifications to your phone.</li>
        <li><strong>Google Maps</strong> draws the map on Android phones.</li>
        <li><strong>A road routing service based on OpenStreetMap data</strong> receives the pick-up and destination points to draw the road route.</li>
    </ul>

    <h2>How long we keep it</h2>
    <ul>
        <li>Your data stays while your account exists. When you delete your account, your name, number, emergency contacts, trips, chat photos and (for drivers) documents are erased for good.</li>
        <li>Notifications in the app are deleted automatically after 30 days if you read them and after 90 days if you did not.</li>
        <li>A sign-in code works for only 5 minutes.</li>
    </ul>

    <h2>Your choices and rights</h2>
    <ul>
        <li>You can delete your account in the app: Account, then Delete account. See <a href="{{ route('legal.account-deletion') }}">how to delete your account</a>.</li>
        <li>You can ask to see, correct or delete the data we keep about you, and you can object to how we use it. Write to us at the contact below.</li>
        <li>If you think your data was handled wrongly, you can complain to the National Privacy Commission of the Philippines.</li>
    </ul>

    <h2>Security</h2>
    <p>
        The app talks to our server over HTTPS. Driver documents and chat photos are kept in private storage and are shown only to the
        people who need them. No system is perfect, so please keep your phone safe.
    </p>

    <h2>Children</h2>
    <p>{{ config('app.name') }} is not meant for children under 13. Drivers must be of legal age to drive.</p>

    <h2>Changes</h2>
    <p>If we change this policy, we put the new version on this page and change the date at the top.</p>
@endsection
