<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyControllerBackup extends Controller
{
    /**
     * The frontend makes an async request to this endpoint to get the options for registering a passkey.
     * The options are then used to create a new passkey.
     */
    public function registerOptions(Request $request)
    {
        $request->validateWithBag('createPasskey', [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $options = new PublicKeyCredentialCreationOptions(
            // The 'relying party'
            rp: new PublicKeyCredentialRpEntity(
                // name of our application
                name: config('app.name'),
                // domain (not including subdomain or protocol) of our application
                id: parse_url(config('app.url'), PHP_URL_HOST),
            ),
            user: new PublicKeyCredentialUserEntity(
                // unique identifier for the user
                name: $request->user()->email,
                // a unique ID of the user that is non-personal
                id: $request->user()->getKey(),
                // friendly display name
                displayName: $request->user()->name,
            ),
            // important if doing attestation, which we are not but must include
            challenge: Str::random(),
            authenticatorSelection: new AuthenticatorSelectionCriteria(
                // the specified authenticators that can be used. no preference = platform OR cross-platform
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
                // make authentication flow smoother
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            )
        );

        // flashed to session for use in the store request - if stateless, we'd have to store this somewhere else
        // like send it back in the response for storage in local storage?
        Session::flash('passkey-registration-options', $options);

        // returns to the frontend, which will use this to create a new passkey using the authenticator of the user's choice
        return (new WebauthnSerializerFactory(
            AttestationStatementSupportManager::create()
        ))->create()->serialize(data: $options, format: 'json');
    }

    /**
     * The frontend makes an async request to this endpoint to get the options for authenticating a passkey.
     */
    public function authenticateOptions()
    {
        $options = new PublicKeyCredentialRequestOptions(
            challenge: Str::random(),
            // must use the same as the RP id used in the registration
            rpId: parse_url(config('app.url'), PHP_URL_HOST),
            // some other options but we are not using them
//            allowCredentials:
        );

        // flashed to session for use in the authentication request - if stateless, we'd have to store this somewhere else
        // like send it back in the response for storage in local storage?
        Session::flash('passkey-authentication-options', $options);

        return (new WebauthnSerializerFactory(
            AttestationStatementSupportManager::create()
        ))->create()->serialize(data: $options, format: 'json');
    }
}
