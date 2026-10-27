// Messages and series shown on the home and Watch pages.
// `url` is where a message plays (e.g. its YouTube link). '#' until videos are linked.

export type Swatch = 'navy' | 'gold' | 'mid' | 'ice' | 'wash' | 'night';

export type MessageType = 'sunday' | 'midweek' | 'special' | 'podcast';

export type Message = {
  type: MessageType;
  kind: string;
  title: string;
  meta: string;
  length: string;
  swatch: Swatch;
  url: string;
};

export const messageFilters: { id: MessageType | 'all'; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'sunday', label: 'Sunday service' },
  { id: 'midweek', label: 'Midweek' },
  { id: 'special', label: 'Special services' },
  { id: 'podcast', label: 'Podcast' },
];

export const messages: Message[] = [
  { type: 'sunday', kind: 'Sunday service', title: 'A royal priesthood', meta: 'Crowned for More · Week 3', length: '42 min', swatch: 'navy', url: '#' },
  { type: 'sunday', kind: 'Sunday service', title: 'Chosen, not forgotten', meta: 'Crowned for More · Week 2', length: '38 min', swatch: 'gold', url: '#' },
  { type: 'sunday', kind: 'Sunday service', title: 'Know whose you are', meta: 'Crowned for More · Week 1', length: '45 min', swatch: 'mid', url: '#' },
  { type: 'midweek', kind: 'Midweek', title: 'Praying with confidence', meta: 'Midweek prayer', length: '31 min', swatch: 'ice', url: '#' },
  { type: 'podcast', kind: 'Podcast', title: 'Faith at work in your 20s', meta: 'Tribe Talk', length: '29 min', swatch: 'wash', url: '#' },
  { type: 'special', kind: 'Special service', title: 'Thanksgiving night', meta: 'Special service', length: '1 hr 12 min', swatch: 'night', url: '#' },
];

export type Series = {
  title: string;
  /** Second line of the title, shown in the accent colour. */
  accent?: string;
  label: string;
  style: 'navy' | 'gold' | 'ice';
  url: string;
};

export const series: Series[] = [
  { title: 'Crowned', accent: 'for More', label: 'Now showing · 3 episodes', style: 'navy', url: '#' },
  { title: 'Tribe', accent: 'Life', label: '5 episodes', style: 'gold', url: '#' },
  { title: 'Kingdom', accent: 'Builders', label: '4 episodes', style: 'ice', url: '#' },
];

export const upcomingLive = [
  { title: 'Midweek prayer', when: '[Day] · [Time]' },
  { title: 'Worship Night', when: 'Fri 7 Nov · 6:00 PM' },
  { title: 'Royal Youth Convention', when: 'Sat 14 Nov · 4:00 PM' },
];
