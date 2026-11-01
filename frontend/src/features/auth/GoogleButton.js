'use client';

import { useEffect, useRef, useState } from 'react';
import Alert from '@/components/ui/Alert';
import { loginWithGoogle } from '@/features/auth/actions';

const SCRIPT_ID = 'google-gsi';

// "Continue with Google". Renders nothing until the server has a Google client id.
export default function GoogleButton({ clientId, text = 'continue_with' }) {
  const box = useRef(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!clientId) return;

    function render() {
      window.google.accounts.id.initialize({
        client_id: clientId,
        callback: async ({ credential }) => {
          const result = await loginWithGoogle(credential);
          // On success the action redirects; only a failure comes back here
          if (result?.message) setError(result.message);
        },
      });
      window.google.accounts.id.renderButton(box.current, { theme: 'outline', size: 'large', text, width: 300 });
    }

    if (window.google?.accounts?.id) {
      render();
    } else if (!document.getElementById(SCRIPT_ID)) {
      const script = document.createElement('script');
      script.id = SCRIPT_ID;
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.onload = render;
      document.body.appendChild(script);
    }
  }, [clientId, text]);

  if (!clientId) return null;

  return (
    <div className="space-y-2">
      <div ref={box} className="flex min-h-11 justify-center" />
      <Alert message={error} />
    </div>
  );
}
