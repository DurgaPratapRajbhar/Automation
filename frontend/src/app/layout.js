import './globals.css';

export const metadata = {
  title: 'Automation',
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
