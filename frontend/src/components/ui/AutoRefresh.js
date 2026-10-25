'use client';

import { useRouter } from 'next/navigation';
import { useEffect } from 'react';

// Re-fetches the server page every few seconds, so new WhatsApp messages appear on their own
export default function AutoRefresh({ seconds = 15 }) {
  const router = useRouter();

  useEffect(() => {
    const timer = setInterval(() => router.refresh(), seconds * 1000);
    return () => clearInterval(timer);
  }, [router, seconds]);

  return null;
}
