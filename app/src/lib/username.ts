// A preview of the username the API will derive from a business name, shown
// on the register screen while the person has not typed a username of their
// own. The API's CreateNewUser is the authority: it runs the same steps and
// then appends a number if the result is too short, reserved or taken. This
// helper only does the first part, so what it shows is a preview, not a
// promise.
export function deriveUsername(businessName: string): string {
  const lettersAndDigits = businessName.toLowerCase().replace(/[^a-z0-9]/g, '')

  return lettersAndDigits.replace(/^[0-9]+/, '')
}
