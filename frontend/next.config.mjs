const nextConfig = {
  // Public test domain (ngrok) may load dev assets; comma separated hosts
  allowedDevOrigins: (process.env.ALLOWED_DEV_ORIGINS ?? '').split(',').map((host) => host.trim()).filter(Boolean),

  // One public domain for all: the site, Meta's WhatsApp webhook and the website-chatbot API (v1),
  // the last two passed on to the backend
  async rewrites() {
    return [
      { source: '/api/webhooks/:path*', destination: `${process.env.BACKEND_URL}/api/webhooks/:path*` },
      { source: '/api/v1/:path*', destination: `${process.env.BACKEND_URL}/api/v1/:path*` },
    ];
  },
};

export default nextConfig;
