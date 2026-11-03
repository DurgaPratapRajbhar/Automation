import './globals.css';

export const metadata = {
  title: { default: 'ScriptDP Automation', template: '%s | ScriptDP Automation' },
  description: 'WhatsApp AI replies, one inbox and customer details for your shop.',
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
