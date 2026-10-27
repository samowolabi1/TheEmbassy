// Events calendar. `month` groups events into the tabs on the Events page.
// Day names are copied from the design; check them against the real calendar before launch.

export type ChurchEvent = {
  month: string;
  mon: string;
  day: string;
  tag: string;
  title: string;
  when: string;
  where: string;
  cta: string;
  url: string;
  calendarUrl: string;
  highlight?: boolean;
};

export const featuredEvent = {
  title: 'Royal Youth Convention',
  dateLabel: 'Sat 14 November',
  /** Used for the countdown. */
  startsAt: '2026-11-14T16:00:00+01:00',
  summary: 'Worship, honest conversations and the biggest Tribe hangout of the year.',
  where: 'Main sanctuary, 4:00 PM.',
  speakers: '[#]',
  entry: '₦[0]',
  ticketUrl: '#',
  calendarUrl: '#',
};

export const months = [
  { id: 'nov', label: 'November' },
  { id: 'dec', label: 'December' },
];

export const events: ChurchEvent[] = [
  { month: 'nov', mon: 'NOV', day: '07', tag: 'Worship', title: 'Worship Night', when: 'Fri · 6:00 PM', where: 'Main sanctuary', cta: 'RSVP', url: '#', calendarUrl: '#' },
  { month: 'nov', mon: 'NOV', day: '14', tag: 'Convention', title: 'Royal Youth Convention', when: 'Sat · 4:00 PM', where: 'Main sanctuary', cta: 'Get ticket', url: '#', calendarUrl: '#', highlight: true },
  { month: 'nov', mon: 'NOV', day: '22', tag: 'Career', title: 'Career & Purpose Clinic', when: 'Sat · 11:00 AM', where: 'Hall B', cta: 'Register', url: '#', calendarUrl: '#' },
  { month: 'nov', mon: 'NOV', day: '30', tag: 'Thanksgiving', title: 'Thanksgiving Sunday', when: 'Sun · [Time]', where: 'Main sanctuary', cta: 'RSVP', url: '#', calendarUrl: '#' },
  { month: 'dec', mon: 'DEC', day: '05', tag: 'Outreach', title: 'Community Outreach', when: 'Sat · 9:00 AM', where: '[Location]', cta: 'Volunteer', url: '#', calendarUrl: '#' },
  { month: 'dec', mon: 'DEC', day: '20', tag: 'Carols', title: 'Carols by Candlelight', when: 'Sat · 6:00 PM', where: 'Main sanctuary', cta: 'RSVP', url: '#', calendarUrl: '#' },
];
