Repositories/  → Repository Pattern
Services/      → Service Pattern
Channels/      → Strategy Pattern
Integrations/  → Adapter Pattern
Events/        → Observer/Event Pattern
Providers/     → Provider Strategy
Factories/     → Factory Pattern


ChannelService
      ↓
ChannelProviderFactory       ← Factory Pattern
      ↓
MetaWhatsAppProvider         ← Strategy implementation
      ↓
ChannelProviderInterface     ← Strategy contract