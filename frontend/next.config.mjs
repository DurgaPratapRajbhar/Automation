const nextConfig = {
  // Public test domain (ngrok) may load dev assets; comma separated hosts
  allowedDevOrigins: (process.env.ALLOWED_DEV_ORIGINS ?? '').split(',').map((host) => host.trim()).filter(Boolean),

  // One public domain for both: the site, and Meta's WhatsApp webhook passed on to the backend
  async rewrites() {
    return [
      { source: '/api/webhooks/:path*', destination: `${process.env.BACKEND_URL}/api/webhooks/:path*` },
    ];
  },
};

export default nextConfig;
