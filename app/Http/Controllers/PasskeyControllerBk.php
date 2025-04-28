<?php

namespace App\Http\Controllers;

use App\Models\Passkey;
use App\Support\JsonSerializer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialSource;

class PasskeyControllerBk extends Controller
{
    public function store(Request $request)
    {
        // some basic validation
        $data = $request->validateWithBag('createPasskey', [
            'name' => ['required', 'string', 'max:255'],
            'passkey' => ['required', 'json'],
        ]);

        // deserialize the passkey that has been given to us by the frontend
        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = JsonSerializer::deserialize($data['passkey'], PublicKeyCredential::class);

        // check that it's the right type. we should never be storing a authentication/login passkey, only an
        // attestation/registration passkey that has been created with storing in mind.
        if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            // it's not a registration of a new passkey, this should never actually happen. security fallback.
            return response()->json([
                'error' => 'Invalid passkey response',
            ], 422);
        }

        try {
            // This is where we validate the passkey properly and make sure it's not been tampered with
            $publicKeyCredentialSource = AuthenticatorAttestationResponseValidator::create((new CeremonyStepManagerFactory)->creationCeremony())
                ->check(
                    authenticatorAttestationResponse: $publicKeyCredential->response,
                    // This could come from the app sending us a value from local storage rather than the session
                    publicKeyCredentialCreationOptions: Session::get('passkey-registration-options'),
                    host: parse_url(config('app.url'), PHP_URL_HOST),


                );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'name' => 'The given passkey is invalid.',
            ])->errorBag('createPasskey');
        }

        // the public key credential source is valid and able to be stored. let's do that.
        $request->user()->passkeys()->create([
            'name' => $data['name'],
            'credential_id' => $publicKeyCredentialSource->publicKeyCredentialId,
            // the validated passkey data, which is automatically cast to JSON
            // had to use a solution from https://laracasts.com/series/add-passkeys-to-a-laravel-app/episodes/3?reply=32204
            'data' => (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
                ->create()
                ->serialize($publicKeyCredentialSource, 'json')
       ]);

        return Redirect::route('profile.edit')->with('status', 'passkey-created');
    }

    public function authenticate(Request $request)
    {

        // some basic validation
        $data = $request->validate( ['answer' => ['required'],]);

        // deserialize the answer that has been given to us by the frontend
        $publicKeyCredential = JsonSerializer::deserialize($data['answer'], PublicKeyCredential::class);

        // check that it's the right type. we should never be validating an attestation/new passkey, only a
        // stored passkey
        if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            // it's not a authentication attempt but a registration/attestation attempt, this should never actually happen. security fallback.
            return to_route('profile.edit');
        }

        $passKey = Passkey::firstWhere(['credential_id' => $publicKeyCredential->rawId]);

        if (!$passKey) {
            // The user has deleted the passkey from their account (in our DB) but not from their device
            throw ValidationException::withMessages([
                'name' => 'This passkey is not valid.',
            ]);
        }

        try {
                // This is where we validate the passkey properly and make sure it's not been tampered with
                $publicKeyCredentialSource = AuthenticatorAssertionResponseValidator::create(
                    (new CeremonyStepManagerFactory())->requestCeremony()
                )->check(
                    // the stored passkey source data in the DB, we give this to the validator
                    publicKeyCredentialSource: $passKey->data,
                    // authenticatorAssertionResponse takes the user information the pass key has provided, and includes information required to validate that this is indeed the right user
                    authenticatorAssertionResponse: $publicKeyCredential->response,
                    // This could come from the app sending us a value from local storage rather than the session
                    publicKeyCredentialRequestOptions: Session::get('passkey-authentication-options'),
                    host: $request->getHost(),
                    userHandle: null,
                );
        } catch (\Throwable $e) {
            dd($e);
            throw ValidationException::withMessages([
                'name' => 'This passkey is not valid.',
            ]);
        }

        Auth::loginUsingId($passKey->user_id);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    public function destroy(Passkey $passkey)
    {
        $passkey->delete();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
}
