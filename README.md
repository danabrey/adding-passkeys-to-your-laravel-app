### Passkey providers:

OS level - e.g. Apple Keychain, Google Password Manager, Windows Hello
3rd party - e.g. Bitwarden, 1Password, KeePass etc.

(all technically 'platform providers' even though 3rd party are not technically only stored on the device, they don't need extra hardware or something off the device)

#### Two types of provider:

- platform, e.g. all of the above
- cross-platform, e.g. yubikey or titan security key, any fido2-compliant external token

# Steps:

## 1. Registering a passkey

### I. Creating passkey options

Like giving a customer a menu in a restaurant, our server tells the browser/JS which options are available of the webauthn standards. Asynchronous options request.

PublicKeyCredentialCreationOptions =
a) an 'RP' PublicKeyCredentialRpEntity, who we are. the name of the app, the domain name we're on, used to store inside the authenticator and display correct info to user. domain supports all subdomains.
b) a 'challenge', a random string
c) a user, a PublicKeyCredentialUserEntity - a 'name' which is a unique piece of info to identify the user.

### II. Creating the passkey

Now we have the options, call the JS APIs necessary to create a passkey in our authenticator of choice. Lots of boilerplate, so we use a JS package now. @simplewebauthn/browser. Library uses the startRegistration() with the options given to us by the api endpoint that uses the PHP package

### III. Storing the passkey 

Validate and store the passkey. Attestation = registering and storing a key, Assertion = logging in and authenticating

a) validate the attestation data ourself in a basic fashion, make sure the passkey is required and json. Then serialize it to json using the package helper (WebauthnSeializerFactory etc)

b) AuthenticatorAttestationResponseValidator validates the: request host, the options (from step 1). Step 1 now flashes the options to the session, and we re-use them here. (not sure if this would work for the app, which would be stateless, we may need to store the options created somewhere else, maybe in localstorage temporarily?), and the Attestation data

PublicKeyCredential is used both for registering and authenticating

## 2. Authenticating via passkey

Much as registration takes place in two steps (options then storing), so does authentication.

Picture a spy thriller. secret MI5 base that is pretending to be a bookshop. In walks a secret agent holding an umbrella, starts making smalltalk. The bookseller "challenges" the agent by saying "terrible weather out" which is a coded message. Agent says "good job i have my umbrella" which is the exact right response. Server is the shopkeeper, browser is the agent. Passkey knows how to answer the challenge.

### I. Creating authentication options

Like registration, we need to create options for the authentication. This is a PublicKeyCredentialRequestOptions object. It contains:

a) a challenge, a random string
b) our RP ID (the domain)

This is passed back in the response so the JS can use it to get the user to choose a passkey to use, via startAuthentication().

### II. Authenticating

We send the data from the JS library to the authenticate() method of our PasskeyController. Accepts the request in a guest route.


