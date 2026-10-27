// Church details and links used across the site.
// Values in [brackets] are placeholders from the design: replace them with real details.

export const church = {
  name: 'RCCG The Royal Tribe Embassy',
  shortName: 'The Royal Tribe',
  tagline: 'A youth parish of The Redeemed Christian Church of God.',
  address: '[Venue address]',
  sundayTime: '[Time]',
  midweekDay: '[Midweek day]',
  midweekTime: '[Time]',
  handle: '@[yourhandle]',
  directionsUrl: '#',
  bank: {
    name: '[Bank name]',
    accountNumber: '[Account number]',
    accountName: 'RCCG The Royal Tribe Embassy',
  },
};

export const links = {
  youtube: '#',
  youtubeLive: '#',
  instagram: '#',
  tiktok: '#',
  facebook: '#',
  x: '#',
  whatsapp: '#',
  spotify: '#',
  applePodcasts: '#',
  audiomack: '#',
  appStore: '#',
  googlePlay: '#',
  login: '#',
  contact: '#',
  privacy: '#',
  terms: '#',
  rccgBeliefs: 'https://www.rccg.org',
};

export type NavId = 'home' | 'about' | 'watch' | 'blog' | 'events' | 'market' | 'prayer';

export const nav: { id: NavId; label: string; href: string }[] = [
  { id: 'home', label: 'Home', href: '/' },
  { id: 'about', label: 'About', href: '/about' },
  { id: 'watch', label: 'Watch', href: '/watch' },
  { id: 'blog', label: 'Blog', href: '/blog' },
  { id: 'events', label: 'Events', href: '/events' },
  { id: 'market', label: 'Tribe Market', href: '/#market' },
  { id: 'prayer', label: 'Prayer', href: '/#prayer' },
];

// Shown in the gold bar above the header. Set to null to hide it.
export const banner: { text: string; cta: string; href: string } | null = {
  text: 'Royal Youth Convention tickets are open',
  cta: 'Register now',
  href: '/events',
};

// Form endpoints and payment keys come from environment variables (see .env.example).
export const config = {
  prayerEndpoint: import.meta.env.PUBLIC_PRAYER_ENDPOINT ?? '',
  newsletterEndpoint: import.meta.env.PUBLIC_NEWSLETTER_ENDPOINT ?? '',
  paystackKey: import.meta.env.PUBLIC_PAYSTACK_KEY ?? '',
};
