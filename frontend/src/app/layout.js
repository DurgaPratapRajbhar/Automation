import './globals.css';

export const metadata = {
  title: { default: 'ScriptDP Automation', template: '%s | ScriptDP Automation' },
  description: 'WhatsApp AI replies, one inbox and customer details for your business.',
  icons: { apple: '/icons/apple-touch-icon.png' },
  // iPhone: open full screen like an app after "Add to Home Screen"
  appleWebApp: { capable: true, title: 'ScriptDP', statusBarStyle: 'default' },
};

// Colour of the phone's top bar
export const viewport = { themeColor: '#0e1428' };

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
