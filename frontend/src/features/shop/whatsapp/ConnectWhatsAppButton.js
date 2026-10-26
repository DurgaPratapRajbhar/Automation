'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import Alert from '@/components/ui/Alert';
import Button from '@/components/ui/Button';
import { connectWhatsApp } from '@/features/shop/whatsapp/actions';

const SDK_ID = 'facebook-jssdk';

// The popup reports the chosen WABA + number through a window message, a moment before
// FB.login's callback gives the code. Wait a little for that message before giving up.
function waitFor(ref, ms = 4000) {
  return new Promise((resolve) => {
    const started = Date.now();
    const tick = () => {
      if (ref.current) return resolve(ref.current);
      if (Date.now() - started > ms) return resolve(null);
      setTimeout(tick, 100);
    };
    tick();
  });
}

// "Connect WhatsApp": opens Meta Embedded Signup and hands the result to our backend
export default function ConnectWhatsAppButton({ signup }) {
  const [ready, setReady] = useState(false);
  const [state, setState] = useState({});
  const [pending, startTransition] = useTransition();
  const session = useRef(null);

  useEffect(() => {
    window.fbAsyncInit = () => {
      window.FB.init({ appId: signup.app_id, autoLogAppEvents: true, xfbml: true, version: signup.graph_version });
      setReady(true);
    };

    if (window.FB) {
      window.fbAsyncInit();
    } else if (!document.getElementById(SDK_ID)) {
      const script = document.createElement('script');
      script.id = SDK_ID;
      script.src = 'https://connect.facebook.net/en_US/sdk.js';
      script.async = true;
      script.defer = true;
      script.crossOrigin = 'anonymous';
      document.body.appendChild(script);
    }

    function onMessage(event) {
      // Only trust messages from Meta
      if (!event.origin.endsWith('facebook.com')) return;

      let message;
      try {
        message = JSON.parse(event.data);
      } catch {
        return;
      }
      if (message.type !== 'WA_EMBEDDED_SIGNUP') return;

      const data = message.data ?? {};

      if (message.event === 'FINISH' && data.phone_number_id && data.waba_id) {
        session.current = { wabaId: data.waba_id, phoneNumberId: data.phone_number_id };
      } else if (message.event === 'CANCEL' || message.event === 'ERROR') {
        setState({ error: data.error_message || `Signup was closed at step: ${data.current_step ?? 'unknown'}.` });
      } else if (message.event?.startsWith('FINISH')) {
        // e.g. only a business account and no number, or the WhatsApp Business app (not supported yet)
        setState({ error: 'Please finish the signup by choosing a phone number.' });
      }
    }

    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, [signup]);

  function start() {
    setState({});
    session.current = null;

    // FB.login needs a plain (non-async) callback
    window.FB.login(
      (response) => {
        const code = response.authResponse?.code;
        if (!code) {
          setState((current) => current.error ? current : { error: 'The popup was closed before finishing.' });
          return;
        }

        waitFor(session).then((chosen) => {
          if (!chosen) {
            setState({ error: 'Meta did not tell us which number you chose. Please try again.' });
            return;
          }

          startTransition(async () => {
            const result = await connectWhatsApp({ code, ...chosen });
            setState(result.ok ? { message: result.message } : { error: result.message });
          });
        });
      },
      {
        config_id: signup.config_id,
        response_type: 'code',
        override_default_response_type: true,
        extras: { setup: {} },
      }
    );
  }

  return (
    <div className="space-y-3">
      <Alert message={state.message} type="success" />
      <Alert message={state.error} />
      <Button type="button" onClick={start} disabled={!ready || pending} pending={pending}>
        {ready ? 'Connect WhatsApp' : 'Loading…'}
      </Button>
    </div>
  );
}
