import './bootstrap';

import Alpine from 'alpinejs';
import {browserSupportsWebAuthn, startAuthentication, startRegistration} from "@simplewebauthn/browser";

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    // data can be reused across application with x-data="registerPasskey"
    Alpine.data('registerPasskey', () => ({
        name: '',
        errors: null,
        browserSupportsWebAuthn,

        // form is from passing ($el) in the template
        async register(form) {
            this.errors = null;

            if (!this.browserSupportsWebAuthn()) {
                return;
            }

            // get the "menu" of passkey options from an endpoint that uses the web-auth/webauthn-lib Composer package
            // it also stores the options in the session for re-use when validating (NOT authenticating) the passkey later
            const options = await axios.get('/api/passkeys/register', {
                params: { name: this.name },
                validateStatus: (status) => [200, 422].includes(status),
            });

            if (options.status === 422) {
                this.errors = options.data.errors;
                return;
            }

            try {
                // uses @simplewebauthn/browser to start the passkey registration process
                const passkey = await startRegistration(options.data);
            } catch (e) {
                // can be triggered if the user closes the dialog or the request times out
                this.errors = { name: ['Passkey creation failed. Please try again.'] }
                return;
            }

            // hook created just before a form is submitted. allows you to alter and mutate any of the form data

            // we send the created passkey from the authenticator back to our server. in the app, we'd
            // also need to send back the options here as they won't be available in the session.
            form.addEventListener('formdata', ({ formData }) => {
                formData.set('passkey', JSON.stringify(passkey));
            });

            // we manually submit the form after the passkey is created
            form.submit()
        }
    }));

    // used in login.blade.php
    // Alpine.data('authenticatePasskey', () => ({
    //     async authenticate(form) {
    //         // get the simple options from the server, consisting of a 'challenge' and a rpId (which is our domain)
    //         const options = await axios.get('/api/passkeys/authenticate');
    //
    //         // pass this to the webauthn JS API to start the authentication process
    //         const answer = await startAuthentication({
    //             optionsJSON: options.data,
    //         });
    //
    //         form.action = '/passkeys/authenticate';
    //
    //         // hook created just before a form is submitted. allows you to alter and mutate any of the form data
    //
    //         // we send the created passkey from the authenticator back to our server. in the app, we'd
    //         // also need to send back the options here as they won't be available in the session.
    //         form.addEventListener('formdata', ({ formData }) => {
    //             formData.set('answer', JSON.stringify(answer));
    //         });
    //
    //         // we manually submit the form after the passkey is created
    //         form.submit();
    //     }
    // }));

    Alpine.data('authenticatePasskey', () => ({
        browserSupportsWebAuthn,

        async authenticate(form) {
            if (!browserSupportsWebAuthn()) {
                return;
            }

            const options = await axios.get('/api/passkeys/authenticate');
            const answer = await startAuthentication({ optionsJSON: options.data });

            form.action = '/passkeys/authenticate';
            form.addEventListener('formdata', ({formData}) => {
                formData.set('answer', JSON.stringify(answer));

                console.log(formData);
            });
            form.submit();
        }
    }));
});

Alpine.start();
