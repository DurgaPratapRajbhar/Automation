// What the phone reads to install the app: name, icons, colours, and that it opens full screen.
export default function manifest() {
  return {
    name: 'ScriptDP Automation',
    short_name: 'ScriptDP',
    description: 'WhatsApp AI replies, one inbox and customer details for your shop.',
    start_url: '/dashboard',
    scope: '/',
    display: 'standalone',
    orientation: 'portrait',
    background_color: '#0e1428',
    theme_color: '#0e1428',
    icons: [
      { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
      { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
      { src: '/icons/maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
    ],
  };
}
