// Small Base64 encoded WAV file for a subtle pop/bubble sound effect
const POP_SOUND_B64 =
  'data:audio/wav;base64,UklGRq4AAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YWoAAACAAJkAmQCUAJEAiwCHAIEAewB1AHAAbQBnAGUAaQBsAHQAgQCVAMIBCQMyBV4HOQhdCVcKLwpcCkcKNQpECUMJQAlMCVEJUglDCUcJYQlPCWMJUQlcCVoJSglACUIJQQk/CTgJOQk3CTAJJwg2CDgIPgg4CDsIMwgzCTEIMQgnCDIIIwgiCB8IJQgcCB0IHgghCB0IHggeCBg=';

let popAudio: HTMLAudioElement | null = null;

export function playPopSound() {
  try {
    if (!popAudio) {
      popAudio = new Audio(POP_SOUND_B64);
      popAudio.volume = 0.5; // Keep the sound subtle
    }

    // Reset playback position if it's already playing so it overlaps correctly on rapid fire
    popAudio.currentTime = 0;
    void popAudio.play();
  } catch (e) {
    // Silently fail if audio playback is blocked by browser policies
    console.warn('Failed to play pop sound', e);
  }
}
