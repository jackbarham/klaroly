# Klaroly: Business Logic and Feature Register

**Name:** Klaroly
**Version:** 0.2, full ambition draft
**Date:** 31 August 2026
**Companion to:** `Technical-Initial-scope-and-research.md` (how it is built) and `decision-log.md` (why)

---

## 0. Where this document is superseded

This is the product specification, written on 31 August 2026 and describing behaviour.
It is **not** the authority on names, types or storage. Where it disagrees with
`database-schema.md`, `CLAUDE.md` or the decision log, those win, because they were
written later and against real code.

Three disagreements are known and settled, listed here so nobody re-derives the wrong
answer from the prose below:

| This document says | The settled answer | Where |
|---|---|---|
| Event location types are *at artist*, *at client*, *at venue* (4.1) | `base`, `client`, `venue`, and the column is nullable | `database-schema.md` 5.9, `App\Enums\LocationType` |
| The event types are *trial* and *wedding* (4.1) | Eight values, and the wedding day is `main` | `database-schema.md` 5.9 |
| Store every event as a UTC timestamp (4.1, date rule 1) | Local wall clock plus an IANA zone; the API sends the calendar date as `YYYY-MM-DD` | `CLAUDE.md`, decision log |

If you find a fourth, add it here rather than fixing the prose in place. The prose is a
record of what was intended in August and is more useful left as it was written.

---

## 1. What this document is, and what it is not

This is **the whole thing**. Everything the product could eventually be, written down in one place so that nothing has to be remembered or rediscovered later.

It is deliberately **not a build order and not a prototype spec**. Version 0.1 of this document kept trying to cut things back to something buildable in six weeks, which was the wrong instinct. The point of the exercise is the opposite: get every possibility on paper, take it to Jess and to other artists, find out which twenty per cent actually matters, and only then decide what version one is.

So this document does two jobs:

1. **The reference.** What each feature is, how it behaves, and what it depends on.
2. **The register.** Section 30 lists every feature with a horizon marker, so that prioritising after the artist conversations is a matter of moving rows rather than rereading prose.

Every feature carries one of four markers:

| Marker | Meaning |
|---|---|
| **Core** | Hard to imagine a usable product without it |
| **Soon** | Clearly wanted, obvious follow-on, not needed on day one |
| **Later** | Genuinely wanted, real work, waits for evidence |
| **Idea** | Recorded so it is not lost. Needs a decision before it needs a spec |

Where something is marked Core it does not mean "build it first". It means "if this is missing, the thing is not the product".

**The thing that matters most in this document is section 4.** Those are the decisions that are cheap now and expensive in six months, and they are the reason for writing all of this down before any code exists.

---

## 2. Product principles

Five things that should decide arguments later, when a feature could reasonably go either way.

### 2.1 Tap, tap, done. And sometimes, talk

The artist is on the road. She is in a car, in a hotel corridor, in a chair between clients. **Ninety per cent of the interaction happens on the phone, and almost all of it should be tapping rather than typing.**

Concretely, that means: quantities are steppers not text fields; prices come from the rate card not the keyboard; statuses are buttons not dropdowns; recording a payment is a modal with the likely amounts already on it; and anything that needs a paragraph of thought gets a good default she can accept without reading.

Typing is reserved for the places it cannot be avoided: a note, a one-off email, an amount, editing contract wording. Those are also the places where the desktop web app earns its keep. **The split is not mobile for viewing and desktop for doing. It is phone for the day-to-day, desktop for the sit-down admin session.**

**Voice is the third input, and it belongs wherever typing is unavoidable but the hands are not free.** An artist walking to her car, driving between a trial and a supermarket, or standing in a venue car park with a phone in one hand and a kit bag in the other cannot type and should not have to wait until she can. Anywhere the app currently wants a paragraph, it should also accept a spoken one. Sections 5.5, 5.6 and 19.5 are the three places this matters most.

So the principle is really: **tap where there is a choice to make, talk where there is something to describe, type only when neither will do.**

### 2.2 Toggle, do not build

Wherever a feature could either be configurable or built as a generator, take configurable. The clearest example is the intake form: **there will never be a form builder.** There will be a fixed set of sensible sections that switch on and off. If someone genuinely needs a bespoke questionnaire, Google Forms exists and is free.

The same instinct applies to email templates, agreement wording, rate cards and reminder schedules. Ship a good default, allow an edit, allow a reset, and stop.

### 2.3 Do not spam the bride

Every automated email is a small withdrawal from the artist's relationship with her client. Brides can tell when mail is automated, and a wedding supplier who sends six templated emails feels less personal than one who sends two good ones.

So: **fewer emails than the system is capable of sending**, everything switchable, and a bias towards giving the artist text she can copy into her own email client over sending on her behalf. The full list in section 15 is a menu of what is possible, not a schedule of what ships.

### 2.4 Structure now for what is coming

Several things in this document are years away and should still shape the schema this month, because they are the ones that cannot be retrofitted without a data migration across live accounts. Section 4 lists them. Everything else can wait.

### 2.5 The artist decides, the app suggests

Every automated step has a manual equivalent and an off switch. "She signed a paper copy", "I took the details on the phone", "I sent that email myself". An app that cannot be worked around gets worked around anyway, just outside the app, and then the records are wrong.

---

## 3. The model

```
Account  (the business. One user today, more later)
 ├── User        artist, assistant, collaborator
 └── Contact     the person who books and pays, usually the bride
      └── Booking            starts life as an enquiry
           ├── Event         trial, wedding, second trial
           │    └── location type, address, call time, travel estimate
           ├── Party member  name plus a service from the rate card
           ├── Extra contact groom, planner, venue coordinator, emergency
           ├── Quote line    price snapshot, never a live rate card reference
           ├── Intake form   sent, returned, reviewed
           ├── Agreement     v1, v2, each separately signed, none ever edited
           ├── Invoice       deposit and balance, with due dates
           │    └── Payment  recorded manually, or by Stripe later
           ├── Message       scheduled, sent, cancelled, failed
           ├── Media         inspiration, trial, wedding
           ├── Note          dated, appendable, optionally with a reminder
           ├── Feedback      from the bride and her party after the day
           └── Audit entry   everything that happened, in order
```

**An enquiry is a booking in an early stage, not a separate object.** More on why in section 5.

---

## 4. The decisions that have to be made now

Six things. Each is cheap this month and awkward or expensive later. Everything else in this document can be deferred without penalty.

### 4.1 Events, not date columns [Core]

Agreed. A booking has an `events` table, normally two rows: `trial` and `wedding`. The type is an enum with room in it, so a second trial, a rehearsal or a bridesmaid-only session costs nothing later.

Every event carries its own date, start time, **location type** (`at artist`, `at client`, `at venue`), address, travel estimate, reminder schedule and calendar entry. That last point is the real reason for the table: everything that touches a date is written once rather than twice.

The interface still shows exactly two date fields on a normal booking. The artist never sees the structure.

**On dates generally**, since you mentioned this is not your strongest area, three rules that avoid almost every date bug in this kind of app:

1. Store every event as a **timestamp with time zone**, always in UTC in the database, always rendered in `Europe/London`. Never store a naive date-and-time.
2. Where something is genuinely a date with no time, such as a balance due date, store it as a **date** type, not a timestamp at midnight. Midnight is a time, and it moves when the clocks do.
3. Do all reminder arithmetic in the artist's timezone, not UTC. "Seven days before, at 9am" means 9am as she experiences it, and in the week the clocks change those two are not the same instant.

### 4.2 Contact as its own record [Core]

Agreed, and worth keeping separate. A contact is the person who books and pays. It holds name, email, phone, and optionally a home address, and nothing else. It exists as its own row so that contacts can be listed, searched and reused when the same family books again.

Everyone else on the day is a **party member** on the booking, not a contact. Name plus a service. No email, no account, no history.

**Party members carry a service from the rate card, not an age.** The rate card holds the labels the artist uses: bride, bridesmaid, mother, senior, child, gentleman. Settings hold a plain-English definition of the ones that need it, so that "Child" can display as "Child, under 16" on the form and on the quote, and the artist sets that wording once.

No dates of birth, ever, and no ages. The pricing decision needs a band, and a band is what gets stored. Collecting children's dates of birth through a wedding supplier's form is data you would then have to justify holding, secure and delete, in exchange for nothing.

### 4.3 One bookings table, two views [Core]

An enquiry and a booking are the same record at different stages. The interface shows them as two sections, because that is how the artist thinks about them, but underneath there is one table with a stage column and every other field nullable.

The alternative, two tables and a conversion step, means either copying data between them or joining across them forever, and it means an enquiry that has been half filled in loses something when it converts. A stage change loses nothing.

### 4.4 Confirmed means signed and deposited [Core]

Agreed and fixed. Agreement signed **and** deposit recorded. Anything else is provisional, and provisional does not hold a date.

### 4.5 Account ID on everything from day one [Core]

This is the one structural item that is new since version 0.1, and it is the most important thing in this section.

You want assistants, second artists and booking transfer eventually. Those are not hard features, but they are impossible to add cleanly if every table hangs off `user_id`. Retrofitting means a data migration across live accounts plus a rewrite of every policy and every query.

**Recommendation:**

```
accounts          the business. Today, one per artist
users             a person with a login
account_user      user belongs to account, with a role
bookings          account_id, not user_id
everything else   account_id, or reachable through the booking
```

Today, registering creates one account with one owner. Nothing in the interface mentions accounts, teams or roles. The cost is one extra column and one extra table that stays at one row per account. The saving is that section 20 becomes an additive feature rather than a rebuild.

### 4.6 Price snapshots, never live references [Core]

Agreed. Quote lines copy the price at the moment they are created. Putting the rate card up in January must not rewrite last year's invoices or last year's reporting.

**When does a price lock?** Your instinct was that it locks once a price has been sent, or once an enquiry becomes a booking. I would go slightly further and say the **line is created with a snapshot the moment it is added**, whatever stage the record is at, with a visible "update to current prices" action if she wants it. That way there is never a moment where the number on screen and the number stored are different things, which is where this class of bug actually lives.

### 4.7 A username, claimed at signup [Core]

Agreed, and it belongs in this section rather than in account settings, because a username is only useful if every account has had one since the beginning. Adding them later means either a migration that invents names for existing accounts or a product where half the links are pretty and half are not.

**How it is set.** Derived automatically at signup and editable immediately:

| They entered | Default username |
|---|---|
| Business name "Acme Makeup Ltd" | `acme-makeup` |
| No business name, "Sarah Bennett" | `sarah-bennett` |
| Either, already taken | `sarah-bennett-2` |

Lower case, letters, numbers and hyphens. Validated live so a taken name is obvious before the form is submitted. Business name wins over personal name, since that is what she puts on everything else.

**A reserved list from day one.** Several hundred words that nobody can claim: `admin`, `support`, `help`, `billing`, `account`, `login`, `signup`, `app`, `api`, `www`, `book`, `mail`, `blog`, `pricing`, `about`, `terms`, `privacy`, `contact`, `status`, `security`, plus every route the marketing site currently has and every one it plausibly will. This is five minutes of work now and an unpickable mess later, because the day someone owns `www.klaroly.com/support` is the day the phishing problem starts.

**Changing it.** Allowed, but keep a history table of every username an account has ever held, permanently. Old ones redirect. Nothing released is ever reclaimable by someone else, because a link in a two-year-old email that quietly starts pointing at a different artist is much worse than a dead link.

### 4.8 Where the pretty URLs actually go

Three jobs, and they do not all want the same shape of URL.

**Client pages go on `username.klaroly.com`.** Settled, and it reverses the earlier draft of this section, which argued for a single `book.` host on security grounds. That argument does not survive the detail. The web app's session cookie is scoped to `.klaroly.com` so that `app.` and `api.` can share it, and `book.klaroly.com` sits inside that scope exactly as `sarah-bennett.klaroly.com` does. The two are identical on this point, so it was never a reason to prefer one over the other. What it is a reason for is holding the line on how those pages are built: server-rendered Blade, no JavaScript, every artist-supplied string escaped, a strict CSP and nothing third-party, all of which section 9 of the technical proposal already requires. The risk that remains is stored XSS in artist-authored content reaching another signed-in artist, and it wants revisiting if the agreement editor ever becomes rich text rather than plain text.

**Search is not a consideration here.** These pages carry `noindex` and are never meant to rank, so the point about subdomains splitting search authority applies only to the public profile. That is why the profile stays on a path.

**The reserved list stops being tidiness and becomes safety-critical.** With paths, an artist claiming `pricing` is a routing collision. With hostnames, an artist claiming `app`, `api`, `www`, `mail` or `support` is either an outage or a phishing surface handed to a stranger for the price of one subscription. Every name in DNS use, and every one that plausibly will be, has to be blocked before the first signup.

**One thing to watch.** Corporate mail scanners are already a live concern for the signing flow, per section 9 of the technical proposal. A single stable hostname builds a reputation with Defender, Proofpoint and Mimecast; several hundred one-off hostnames look more like the shape of a phishing campaign. There is nothing to do about it today beyond knowing where to look first if signature rates at corporate, NHS and university addresses turn out worse than everywhere else.

**Not a catch-all that falls through to the marketing site either.** Your instinct was to try the profile first and fall back to a static page. I would invert it: **named routes win, the username route is the last one registered.** With the fallback the other way round, adding a `/pricing` page in eighteen months either breaks an artist who owns that name or silently shadows her. With reserved names plus last-place matching, neither can happen.

**Settled, three different URLs for three different jobs:**

| Purpose | URL | Why |
|---|---|---|
| Client pages: form, agreement, invoice, feedback | `sarah-bennett.klaroly.com/<token>` | The artist's own hostname, which reads as hers rather than the platform's. The username is decoration; the opaque token is still the only thing granting access |
| Public profile, later | `www.klaroly.com/sarah-bennett` | Consolidates search authority onto the marketing domain, which is the one you want ranking |
| The app itself | `app.klaroly.com` | Unchanged |

The important half of the first row: **the username must never be part of what makes a URL secure.** Tokens stay long, random and opaque. Adding a readable name in front of one costs nothing and looks far better in an email, which is the whole point.

One practical note on the profile row. The marketing site is a separate Nuxt codebase on Cloudflare Pages, so `www.klaroly.com/sarah-bennett` means that site rendering a page from an API response rather than Laravel serving it. That is a good outcome, since it gets server rendering and proper meta tags for free, but it is a dependency between two repositories that does not exist today. Worth knowing before it is a surprise.

---

## 5. Enquiries

This has been promoted from a stage to a section of the app, because your point about it is the strongest new idea in the feedback: **the artist is talking to dozens of people about dates she will mostly not book, and she cannot hold that in her head.** An app that helps with that is useful on day one, before a single contract or invoice exists.

### 5.1 Enquiry stages [Core]

| Stage | Meaning | Holds a date? |
|---|---|---|
| **New** | Arrived, not yet looked at | No |
| **In conversation** | Talking, nothing firm | No |
| **Possible** | Looks like it might happen | **Yes, softly** |
| **Quoted** | Price sent, waiting on an answer | Yes, softly |
| **Booked** | Becomes a provisional booking | Yes, properly |
| **Lost** | Did not convert, with a reason | No |

The artist moves an enquiry along by tapping. Moving it to **Possible** is the moment it starts appearing on the calendar as a soft hold, and that is the single interaction the whole feature turns on. It has to be one tap from the enquiry row, with no form in the way, or she will not do it.

### 5.2 Soft holds and clash warnings [Core]

Three kinds of mark on a date:

| Mark | What it is | Behaviour |
|---|---|---|
| **Confirmed** | Signed and deposited | Solid. Warns loudly on any new event |
| **Provisional** | Booking, not yet both | Outlined. Warns |
| **Possible** | Enquiry at Possible or Quoted | Small count badge. Warns gently |

When any event is created or moved onto a date that already carries a mark, the artist sees what is already there and confirms past it. **Never a block.** Two weddings in a day is normal, and a Saturday with four enquiries on it is exactly the situation the warning exists to surface rather than prevent.

The warning is worded so that the useful action is obvious:

> **12 June 2027 already has:**
> Sarah Bennett, confirmed, Hedsor House, 6:30am
> 3 enquiries at Possible
>
> *Continue anyway* | *See what else is on this date*

Your point about the follow-up call is the real value here. If an enquiry went quiet three months ago, override it without a thought. If it was a conversation last week, that is a phone call worth making before someone else takes the date. **The app should show how long ago each enquiry was last touched**, right there in the warning, because that is the fact that decides which of the two it is.

### 5.3 Converting an enquiry [Core]

One tap. The stage moves to provisional, nothing is copied, nothing is re-keyed, and any fields that were never filled in are simply still empty. The enquiry disappears from the enquiries list and appears in bookings, and its soft calendar hold becomes a real one.

Converting is reversible for as long as nothing has been signed, because an artist will sometimes convert optimistically.

### 5.4 How enquiries arrive

Four routes, in ascending order of how much work they are.

**Manual [Core].** Tap the plus, type a name, done. Everything else optional. This has to be fast enough to do during a phone call.

**Hosted enquiry form [Soon].** Each artist gets a public URL. She links to it from her website, puts it in her Instagram bio, or pastes it into a reply. Whoever completes it creates a New enquiry with structured data, and she gets a notification.

**Embeddable form [Later].** The same form as a small embed snippet for WordPress, Squarespace or a hand-built site, so it appears inline on her own contact page rather than sending people elsewhere. This is worth doing eventually because "leaving my website" is a real objection, but it is a genuine piece of work: cross-origin styling, an iframe or a script tag, and a support burden every time someone's theme fights it. The hosted URL gets ninety per cent of the value for ten per cent of the effort, so it goes first and the embed follows if people ask.

**AI-assisted intake [Later].** Described next.

### 5.5 AI-assisted intake [Later]

Three ways in, one mechanism behind them. The mechanism is the interesting part, and the third route is the one I would build first.

**Paste a conversation.** The artist opens New enquiry, taps a text area, and dumps in a WhatsApp thread, a text message chain or a forwarded email. Most natural at a desk, where the conversation is already on a screen next to her.

**Speak it.** Tap the microphone and talk: *"Had a call with Emma Reid, wedding is the 14th of June next year at Hedsor House, four bridesmaids and her mum, wants a trial in the spring, she is going to email me."* The app produces a draft enquiry from that.

This is the route worth building first, because it fits the actual moment. Enquiries do not arrive at a desk, they arrive in a car park after a trial, on a walk, in the ten minutes between two jobs. **Anything that requires sitting down is something that gets postponed and then forgotten**, and a forgotten enquiry is the exact failure the whole enquiries section exists to prevent. Voice works with AirPods in, hands on a steering wheel, kit in both arms.

**Forward an email.** Each artist gets a private intake address, deliberately not guessable, and forwards enquiry emails to it. Lowest effort to use, highest effort to build safely.

### 5.5.1 Rules that apply to all three

1. **Always a draft, never a record.** The extracted fields are shown next to the original text or transcript, field by field, and the artist confirms or corrects before anything is saved.
2. **The source is kept** on the enquiry, so when the extraction is wrong she can see what it was working from. For voice, keep the transcript rather than the audio.
3. **Unsure means empty, not a guess.** A confident wrong wedding date is worse than a blank one, and it will be believed.
4. **Queue it when offline.** A voice note recorded with no signal is transcribed when there is one. The artist should never lose a capture because of where she was standing.

### 5.5.2 Things to know before committing

**Names and venues are what transcription gets wrong.** "Siobhan", "Aoife", "Hedsor", "Botleys Mansion" and every hyphenated surname in Britain. General accuracy figures are irrelevant here, because the fields this feature exists to fill are precisely the hard ones. Two mitigations worth building in: bias the transcription with the artist's existing contacts and venues, which most APIs support, and treat names and venues as the fields most likely to need correcting in the review screen rather than burying them.

**Cost scales with use, not with revenue.** Transcription plus extraction is small per enquiry and unbounded per account. That makes it the natural candidate for plan gating, which is a decision rather than a surprise. See 21.3.

**Privacy.** Sending a client conversation or a recording to a third-party model is a processing activity the artist is responsible for as controller. It needs stating in the privacy policy and in the data processing agreement, and the feature should be off by default with a plain explanation the first time she turns it on.

**The forwarding address needs spam handling** from the start. Simplest safe rule: only accept mail sent from her own registered address, which covers forwarding and blocks everything else.

**Why all of this is worth the trouble** is your framing, and it is right: the feature exists to remove the barrier to logging an enquiry at all. If logging takes ninety seconds of typing, she logs only the ones she thinks will convert, and the calendar warnings are then working from a partial picture. If it takes ten seconds of talking, she logs everything, and every other part of the enquiries feature gets better.

### 5.6 Capturing an enquiry on the spot [Soon]

This is a different feature that looks similar, and I think it is the most commercially interesting idea in your feedback so far.

**The situation.** The artist is at a wedding or a trial. A bridesmaid has just watched her work for four hours, says she is getting married next year, or that a friend has just got engaged. This is the warmest lead in the entire business and it currently disappears, because the artist is holding a brush, running late, and will not remember a surname by Tuesday.

**The interaction.** Tap the plus, tap New enquiry, and either speak it or type just an email address and a first name. Ten seconds, one hand, no form.

**What happens next is the whole feature.** The app immediately sends a short, warm introduction email from the artist:

> Lovely to meet you today. Here are my details so you have them, and do get in touch when you know your date. No rush at all.

Then, three or four days later, a single gentle follow-up. That is it. Two emails, both switchable, both editable.

**Why this is worth more than it looks:**

- **It is a business card that cannot be lost**, sent at the exact moment the person is most impressed. A card goes in a bag and comes out at Christmas.
- **The record exists in the app**, so it appears in the enquiries list and gets chased like anything else, rather than living in the artist's memory.
- **It is a marketing channel that costs nothing**, running on the artist's best possible advertising, which is her work being watched for four hours by six people who are all at weddings frequently.
- **It is a reason to open the app on a working day**, which no other feature in this document gives her.

**Three things to get right:**

**The email must not read like marketing.** It is a solicited reply to someone who asked, and it should sound like the artist rather than like a system. It also needs an unsubscribe link and a line saying who entered the address and why, because the person did not type it themselves. That is both the lawful position and the decent one.

**The chase is one email, not a sequence.** Someone who has not replied after one follow-up is not interested yet, and a third email turns a good impression into an annoying one. If she wants to chase again, she does it herself.

**It has to work with no signal.** Venues are often rural. The capture saves locally and sends when there is a connection, and the artist is told when it has gone.

**And the better way to do the capture itself is to hand the phone over**, which is 5.6.1.

**A note on SMS.** A text message would land better than an email in this specific situation, and it is the one place in the whole product where SMS earns its cost. The technical proposal rules SMS out at roughly 4p a message against a £15 plan, which is correct for reminders sent to every booking. For a handful of introductions a month it is a different calculation. Worth revisiting once there is any evidence about how often this gets used.


### 5.6.1 Hand the phone over [Soon]

The better version of 5.6, and I think it is the right primary interaction rather than an alternative to speaking.

**The interaction.** The artist is at a venue with her hands full. Someone asks for her details. She taps a button in the top corner, the screen empties to three fields, and she hands the phone over. The person types her own name, email address and number, taps Done, and hands it back.

It is better than the artist typing or speaking for one plain reason: **the person types her own email address.** That is the field the whole feature depends on, it is the one an artist standing in a car park will get wrong, and a single wrong character loses the lead silently. It is also faster for the artist, who does not have to stop what she is doing.

### 5.6.2 The five things that decide whether this works

**1. It has to be a genuinely locked screen, not a tidy one.**

This is the most important thing here and the easiest to get wrong. Hiding the navigation is not the same as locking the phone. An unlocked device handed to a stranger with this app open is one back-swipe away from every client's address, phone number and price.

So: a dedicated route with no navigation at all, the Android hardware back button intercepted, swipe-back gestures disabled, nothing tappable except the three fields and Done, and **exit gated behind Face ID or the device passcode**. The app already has biometric lock in section 25, so this reuses something that exists rather than adding a mechanism.

Guided Access on iOS and screen pinning on Android are the operating system's own versions of this and are worth knowing about, but neither can be turned on programmatically, so the lock has to be built in the app.

**2. It must work with no signal, and must not claim otherwise.**

Venues are rural and getting-ready rooms are concrete. The capture saves locally and the email queues.

Which means the screen the guest sees must not say the email has been sent, because sometimes it has not. Something closer to:

> **Thank you.** Sarah will email you her details shortly.

True in both cases, and it costs nothing to word it correctly the first time.

**3. One line of consent, above the button.**

This version is on much firmer ground than 5.6, because the person is entering her own details rather than having them entered for her. Say so plainly and it is finished:

> Sarah will email you her details. You can unsubscribe at any time.

**4. Validate the email, and make a bounce loud.**

Typos happen on someone else's keyboard. Check the format inline before Done will submit. And when the introduction email bounces, surface it to the artist rather than letting it fail quietly, per the delivery status feature in 15.8. She may still have a phone number, and a bounced lead she knows about is recoverable while a silent one is not.

**5. Offer "add another" before handing it back.**

There is rarely only one bridesmaid. After Done, a single button to reset the fields lets the phone go round the group, and each entry is its own enquiry.

### 5.6.3 An optional fourth field

Name, email and phone is the right default. I would allow one optional extra, switchable in settings: **the wedding date, if they know it.**

It is one tap on a date picker, and it is the field that turns a name in a list into something the app can actually work with, because a date is what puts the enquiry on the calendar, triggers the clash warnings in 5.2, and makes it obvious three months later which of eleven names is urgent. Everything else can wait for a conversation.

### 5.6.4 What the artist gets back

Handing the phone back should land her on a short confirmation rather than dropping her into the locked screen again. Then:

- A new enquiry, at stage New
- Her introduction email queued or sent
- A follow-up reminder to **her**, three or four days later: *"You met Emma Reid at Sarah's wedding on 14 June. Worth a follow up?"*

That reminder is separate from the chase sent to the guest, and it matters more. The chase is polite. The reminder is the thing that turns a name typed in a car park into a booking.

### 5.6.5 Where the enquiry came from [Soon]

A small addition that this feature makes worth building, and that pays off later.

**Every enquiry records its source:** manual, website form, forwarded email, voice note, handed over at a booking. When it is the last one, it also records **which booking**, because the artist was working when it happened.

Two reasons that is worth a column. In the short term it gives her context she would otherwise lose: *"this came from Sarah's wedding"* is the fact that makes a name from four months ago mean something. In the longer term it is the only way to answer a genuinely valuable question, which is **which weddings and which venues actually generate more work.** An artist who learns that one venue produces three enquiries a year and another produces none has learned something worth changing her marketing over, and it comes for free from a field she never has to fill in.


---

## 6. The booking lifecycle

Two axes, unchanged from version 0.1 except that the enquiry stages now sit at the front of the first one.

### Axis one: stage

| Stage | Meaning | Advances when |
|---|---|---|
| **New** to **Lost** | The enquiry stages in section 5.1 | Converted |
| **Provisional** | Dates pencilled, price quoted, nothing binding | Agreement signed and deposit recorded |
| **Confirmed** | The date is held | Wedding date passes |
| **Completed** | Work done, balance may be outstanding | Balance settled |
| **Closed** | Done and paid. Archived, read-only | Terminal |
| **Cancelled** | Cancelled after confirmation. Refund rules apply | Terminal |

### Axis two: waiting on

This drives the pills, the home screen and the answer to "what have I not done".

| Waiting on | Trigger | Home screen wording |
|---|---|---|
| *(nothing)* | Nothing outstanding | Not listed |
| **Client: form** | Form sent, not returned | Waiting on Sarah to send her details |
| **Artist: review** | Form returned, not reviewed | Sarah has sent her details |
| **Artist: price** | Reviewed, no quote built | Sarah needs a price |
| **Client: signature** | Agreement sent, unsigned | Waiting on Sarah to sign |
| **Client: deposit** | Invoice raised, deposit unpaid | Waiting on Sarah's deposit |
| **Client: balance** | Balance due date passed | Sarah's balance is overdue |
| **Artist: not held** | Provisional past hold expiry | Sarah's date is not held |
| **Artist: enquiry cold** | Enquiry at Possible, untouched for N days | Emma's enquiry has gone quiet |

**Suppressed by feature toggles.** This is your point from the feedback and it is important enough to state as a rule: if the artist has switched off invoicing, no booking is ever waiting on a deposit. If she has switched off agreements, none is waiting on a signature. The waiting-on calculation reads her enabled features first, so the home screen only ever asks about things she has asked the app to do. Section 21 covers the toggles themselves.

---

## 7. Pricing, quotes and the rate card

### 7.1 The rate card [Core]

A list of services with a default price. Shipped with sensible defaults, all editable, all deletable, and she can add her own.

| Service | Applies to | Notes |
|---|---|---|
| Bride | Wedding | |
| Bridesmaid | Wedding | |
| Mother of the bride | Wedding | |
| Senior | Wedding | Label and definition set in settings |
| Child | Wedding | Label and definition set in settings |
| Gentleman | Wedding | |
| Bridal trial | Trial | Toggle for whether it is included in the bride price |
| Additional trial | Trial | |
| Early start supplement | Wedding | Before a time set in settings |
| Travel | Both | See section 8 |
| **Accommodation** | Both | New. See below |

Each service flags whether it applies to a trial, a wedding day, or both, so the party sheet on a trial does not offer bridesmaid rates she never charges.

**Accommodation and expenses [Core].** You are right that this belongs on the rate card and it is easy to miss. A 6am start two hundred miles away means a hotel the night before, and that is a real cost that has to appear on the quote and the invoice as its own line rather than being buried in a travel figure. I would model it as a small set of **expense lines** rather than one: accommodation, parking, congestion or clean air charges, and a free-text other. All zero by default, all one tap to add.

### 7.2 Building a quote [Core]

Tap quantities: one bride, three bridesmaids, two children, one mother. Lines build themselves. Every line editable, deletable, and a free-text line available for anything the card does not cover. Discount as an amount or a percentage, with a reason.

**Fixed price mode.** One line, one number, one description, for the artist who agrees "£450 all in" on the phone. Switchable at any point before the agreement goes out, which collapses the itemised lines into a total.

### 7.3 Quotes as something she sends herself [Core]

This is the change I like most in your feedback, and I think it generalises.

The artist builds the quote by tapping. The app then produces **a written quote she can either send or take**:

- **Copy to clipboard**, which is the primary action. She pastes it into whatever she was already using: her own email, WhatsApp, Instagram DMs. It comes from her, in her thread, with her signature.
- **Send from the app**, secondary, for when she is happy to let it go out automatically.
- **Download as a PDF**, for when it wants to look formal.

The reason this matters beyond quotes is that it is a much lower-commitment way for an artist to start trusting the product. Copying text out is a favour the app is doing her. Sending on her behalf is a responsibility she has to grant it. **Every template in section 15 should have the same three actions**, and the copy action should be the default until she chooses otherwise.

### 7.4 Deposit and balance [Core]

Default deposit as a fixed amount or a percentage, set once, overridable per booking. Deposit due N days from signature. Balance due N days before the wedding. All settings, all overridable.

---

## 8. Travel

Deliberately simple, and mostly for her own information rather than for charging.

### 8.1 The estimate [Soon]

Artist's base postcode in settings. When a venue address is entered, call the distance API once, **driving only**, and store distance in miles, duration in minutes and the timestamp on the event. Never recompute on screen load.

Displayed under the venue: *32 miles, about 56 minutes by car*. Labelled as a typical journey, because an estimate for a Saturday eighteen months out is a guess and pretending otherwise is worse than saying nothing.

No public transport. As you say, they nearly all drive because of the kit.

### 8.2 Charging for travel [Soon]

A setting: included, free within a radius then charged, per mile, or a flat fee. Per-mile default of 45p, which is the HMRC rate and the number most people already use. Per-booking override on the quote.

### 8.3 Travel alerts on the day [Idea]

Your idea, and a good one. The night before and again early on the morning of an event, check the route for disruption and send a local notification if something has changed materially against the stored estimate.

Worth recording now and building much later, for three reasons: live traffic and incident data is a paid API tier, the check has to run per event per day which is a scheduled job with a cost attached, and a false alarm at 4am is worse than no alarm. But the structure supports it already, because the estimate is stored on the event with a timestamp, so a later comparison has something to compare against.

### 8.4 Maps [Core]

Address, an **Open in Maps** action that hands the address to Apple Maps or Google Maps with directions, and a link to a web search for the venue name.

**On "venue enrichment", which you asked about**: it means pulling in information about the venue itself, so that typing "Hedsor House" would bring back photos, a description, contact details, parking notes, and a record that builds up across bookings. It is a nice idea and it is a whole feature: a venue table, a data source, deduplication of names, and someone maintaining it. I would not do it. A search link answers the same question for nothing. If it ever comes back, the better version is **her own venue notes**, built from her own bookings: "Hedsor House, park round the back, bridal suite is on the second floor, no lift". That is worth more to her than anything an API would return, and it is a small feature rather than a large one. Recorded as an Idea.

---

## 9. The intake form

**There will never be a form builder.** Toggle on, toggle off, and nothing else.

### 9.1 Structure [Later]

A fixed set of sections, each switchable in settings, each switchable again per booking.

| Section | Fields |
|---|---|
| Your details | Name, email, phone, home address |
| Additional contacts | Partner, planner, emergency contact |
| Wedding day | Date, venue name, address, ceremony time, ready-by time |
| Getting ready | Address if different from the venue |
| Trial | Preferred dates, at whose address |
| Your party | Repeating rows: name and service |
| Your look | Free text plus a few style tick boxes |
| Inspiration images | Upload up to N |
| Anything we should know | Free text |
| Photo consent | Yes or no, plus social media yes or no |

Plus a handful of **custom questions** the artist can add: a label and a type from short text, long text, yes or no, and choose from a list. That is not a builder, it is five spare fields, and it is where the line sits.

Exactly which sections ship is a question for the artists rather than for us. Section 31 asks it.

### 9.2 Behaviour [Later]

**Always prefilled** with whatever is already known, **always editable** by the bride, so submitting is a confirmation rather than data entry. It returns to a **review screen** showing what she changed, which the artist accepts or corrects. Nothing overwrites the booking silently.

Same link mechanics as the agreement: tokenised URL, multi-use token, GET renders and POST submits, `noindex` header, expiry with a resend action. Section 9 of the technical proposal has the detail and the reasoning.

If the bride never fills it in, the artist completes it herself and marks it **taken by phone**.

**Photo consent is the one field I would keep even if everything else is switched off.** It costs nothing and it is the difference between a marketing library she can use and one she cannot.

---

## 10. The agreement

### 10.1 Generation and signing [Later]

Global template in settings, seeded with a lawyer-reviewed UK default, editable, with a reset action. Merge fields for names, dates, venue, party, price breakdown, deposit, balance and cancellation terms. Sent as a tokenised link, signed by typed full name plus a ticked box plus a submit, PDF and completion certificate to both parties.

### 10.2 Versioning [Core, as a table]

**A signed agreement is never edited.** If the price or the dates change after signing, the booking gets a new version, generated fresh, sent fresh, signed fresh. Version 1 stays with its own audit trail.

Until the new version is signed, **the previous one is the agreement in force** and the booking says so plainly.

The change flow: the artist edits a price or moves a date on a confirmed booking, and the app asks whether this needs a new agreement. Her call, not the app's. If yes, a new version goes out and the booking waits on a signature while staying confirmed. If no, the change is recorded as an audit entry and nothing is sent.

The versions table is the Core part. The signing feature that sits on top of it can wait.

---

## 11. Invoices and payments

### 11.1 Invoice [Soon]

Raised automatically when an agreement is signed, or manually at any time. Sequential number per account, numbered at issue rather than at draft so there are no gaps. Business details, line items, deposit and balance with due dates, and payment instructions.

**Payment instructions are just a field.** Bank details, a PayPal link, a Stripe link, her own payment page, or a sentence of text. The app should not care. That is the right answer for a market where most people take transfers and a minority take cards.

**Attaching the invoice as a PDF** to the confirmation email makes it feel official, and it is switchable. She can also download it and attach it herself, which fits the copy-rather-than-send principle from 7.3.

### 11.2 Payments [Soon]

Payments are rows, never a boolean, because a wedding booking sits part paid for months. "Paid" is derived from whether payments cover the total.

**Recording one is a modal, not a form.** Tap Record payment and get:

> **Deposit, £200** · **Balance, £250** · **Full amount, £450** · **Other**

Tapping any of the first three fills the amount and today's date and needs one more tap to confirm. Other opens a number pad. Method defaults to bank transfer. Reference optional. That is tap, tap, done, and it is the interaction the artist will perform most often after creating a booking.

Overpayments and part payments both allowed. **Refunds are negative payments** with a reason, which cancellations need.

### 11.3 Chasing [Soon]

Reminder schedules per invoice, switchable per booking, stopping automatically when the balance reaches zero. Plus a **snooze until** action, separate from recording a payment, so an artist who has agreed to wait can stop the emails without falsifying her own figures.

### 11.4 Card payments [Later]

Stripe, optional per artist, never required. When it is connected the deposit and balance links appear on the invoice alongside the bank details, and payments record themselves without the artist tapping anything.

Whether the platform takes a percentage is still open. Section 32.

---

## 12. Feedback and reviews

Two quite different features that share a mechanism, and they should be kept apart in the planning because one is small and one is a product in its own right.

### 12.1 Private feedback on the booking [Later]

After the wedding, an email with a link to a very short form. Two or three questions and a free-text box. It comes back and is logged on the booking.

**Anyone in the party can leave it, not only the bride.** The link is shareable, and the bride's own response is distinguishable because her link went to her verified email address while the others did not. Store that distinction on the response rather than deciding what to do with it now.

This is genuinely small and genuinely useful: it closes the loop on a booking, it gives the artist something to read on a Monday, and it builds a body of material she owns.

### 12.2 A public artist profile with verified reviews [Idea]

The bigger version, and the one worth being careful about.

The proposition is strong. Because a review is tied to a real booking, with a real signed agreement and a real payment, the platform can say something no general review site can: **this person genuinely had their makeup done by this artist on this date.** That is a meaningful claim and it would be a good reason for an artist to send people to her profile.

Three things to understand before committing to it, because they change what the feature is:

**It makes you a review platform, with the obligations that carries.** Under the Digital Markets, Competition and Consumers Act, publishing consumer reviews brings duties around fake and incentivised reviews and around not presenting a misleading picture. That is not a reason to avoid it, but it is a reason not to drift into it by accident.

**The artist will want to hide the bad ones, and that is exactly the thing you cannot let her do.** If she can suppress a negative review, "verified" stops meaning anything and the feature's whole value collapses. The workable position is: she chooses whether her profile is public at all, and once it is, everything published is published, with a right of reply. That is a harder sell to the artist and it is the honest design.

**It changes who the customer is.** A public profile with verified reviews is a directory, and a directory has brides on it looking for artists, and that is a marketplace. That is a much bigger business than a booking tool, with a different growth model and different economics. Worth wanting. Not worth stumbling into.

**Recommendation:** build 12.1, which is small and has no downside. Treat 12.2 as a separate product decision to make deliberately, later, with evidence.

---

## 13. Media

### 13.1 Storage [Soon]

Originals to object storage. The app displays a compressed web derivative, and only fetches an original on demand. Thumbnails sync eagerly, full resolution does not.

From the technical proposal: files in `Directory.LibraryNoCloud` on iOS, uploads queue when there is no signal. The queue matters because a getting-ready room at 6am often has none.

### 13.2 Labels [Soon]

Three, stored on the media record rather than in separate tables:

| Label | Uploaded by | Purpose |
|---|---|---|
| **Inspiration** | The bride, through the form | What she wants |
| **Trial** | The artist | What was done at the trial |
| **Wedding** | The artist | What was done on the day |

Grouped by label on the booking's photos tab. One table, one column, three groups.

### 13.3 Consent [Soon]

The consent answer from the intake form displays on the photos tab, and export or share is disabled with an explanation when it was not given. Small feature, real value, and the alternative is an artist reusing a client's photo without permission, which is the kind of mistake that ends a relationship with a venue.

### 13.4 The photographer's gallery link [Soon]

Your idea, and it is a lovely small feature.

A field on the booking for **the wedding photographer's gallery link**. The thank-you email, or a later one, asks the bride to send it through when it arrives, because the artist would love to see them. When it comes she pastes the link into the booking.

Why it is worth more than it looks: the photographer's images are the good ones. Professional lighting, the full look, the whole day. Those are the pictures an artist actually wants for her own portfolio, and at the moment they live in an email from eighteen months ago that she cannot find. A field on the booking, alongside the consent flag that says whether she can use them, turns a scattered mess into a library.

Just a URL field and a date. Nothing clever.

---

## 14. Notes and reminders

### 14.1 A stream, not a field [Core]

Not one big notes box. **Each note is its own record** with a timestamp, appended to a list, newest first. Free text, private, never sent anywhere, never merged into a template.

This is a better fit for how the information actually arrives: a phone call in March, a change of plan in June, something the mother said at the trial. One box means everything gets overwritten or the box becomes unreadable.

### 14.2 A reminder on a note [Soon]

Your idea. Write a note, tap an alert icon, pick a date, and get a notification on that day with the note text and a link back to the booking.

Implemented as a **local notification** where possible, which means it works with no signal and no server, and it is exactly the pattern the technical proposal already recommends for anything the app knows about in advance. "Ring the venue about parking" in April, surfacing in September, is a small thing that would make an artist trust the app with more.

Reminders should also be creatable without a note, from the booking, for the same reason.

---

## 15. Messages and automation

The engine at the centre of the product, and the part most in need of restraint.

### 15.1 Three tiers of template [Core]

1. **Standard templates**, shipped, written well, so she gets value without writing anything.
2. **Her global overrides**, edited once in settings.
3. **Per-booking overrides**, edited on the individual booking.

### 15.2 Three actions on every template [Core]

From the principle in 7.3, and it applies to all of them:

- **Copy**, which puts the rendered text on the clipboard for her own email client. Default until she says otherwise.
- **Send**, from the app.
- **Automate**, which schedules it for future bookings without asking.

Most artists will start at copy, move some templates to send, and eventually automate the two or three that are genuinely routine. **The app should make that progression easy rather than assuming the end state**, because trusting software to email your clients unsupervised is a big step and it is not one to demand on day one.

### 15.3 Scheduled messages [Soon]

When a booking gets the dates and status a template needs, the app materialises a **scheduled message row**: template, rendered subject and body, send-at time, and a status of pending, sent, cancelled or failed.

**The body is rendered and stored at materialisation, not at send time.** That means the upcoming list shows the exact text that will go out, and editing it edits that text. The cost is that changing a global template does not update already-scheduled messages, which is mitigated by a **regenerate from template** action that re-renders anything pending and unedited while leaving hand-edited messages alone.

### 15.4 The full menu

Recorded as possibilities, not as a schedule. **Expect to ship four or five of these**, which is roughly what artists have said they want: something up front setting out what is needed, something before the trial, something before the wedding, and a thank you afterwards.

| Template | Trigger | Timing |
|---|---|---|
| Enquiry acknowledgement | Enquiry arrives from a web form | Immediate |
| Nice to meet you | On-the-spot capture at a venue | Immediate |
| Nice to meet you, follow-up | On-the-spot capture, unanswered | +3 or 4 days |
| Quote | Artist builds a quote | Manual, copy by default |
| Details form request | Artist sends the form | Immediate |
| Details form reminder | Form outstanding | +3, +7 days |
| Agreement to sign | Artist sends the agreement | Immediate |
| Agreement reminder | Unsigned | +3, +7 days |
| Booking confirmed | Agreement signed | Immediate, PDF attached |
| Invoice and deposit request | Agreement signed | Immediate |
| Deposit reminder | Deposit overdue | Configurable |
| Trial reminder | Trial event | 7 days and 1 day before |
| Trial follow-up | Trial event | 1 day after |
| Balance due | Balance due date | On the date, then chasers |
| Wedding reminder | Wedding event | 14 and 2 days before |
| Thank you | Wedding event | 2 days after |
| Feedback request | Wedding event | 7 days after |
| Photographer gallery request | Wedding event | 8 weeks after |

Nothing here is deleted. Everything is switchable, globally and per booking. An artist who wants two emails gets two emails.

### 15.5 Location-aware wording [Core]

From your brief, and it is a good idea. Not separate templates: **variants inside one template**, selected by the event's location type.

The trial reminder has three short blocks: coming to me, me coming to you, meeting at the venue. She edits three paragraphs once rather than three whole emails.

### 15.6 Merge fields

Bride's first name, artist's name and business name, event date, day of the week, start time, the resolved address, travel time, party size, total, deposit, balance, balance due date, payment instructions, and the artist's sign-off.

### 15.7 Sending [Core]

**From a platform domain, display name set to her business name, reply-to set to her address.** The bride sees "Sarah at Sarah Jones Makeup" and replies land in Sarah's normal inbox.

Custom sending domains would need her to add DNS records, which is a support burden neither of you wants. Agreed and settled.

**Replies go to her inbox, not into the app.** That is right for now, and it means the booking's message list is outbound only. It should be labelled "sent messages" rather than implying it is a conversation. Inbound capture is a Later feature and not a small one.

### 15.8 Delivery status [Soon]

Every message row keeps its scheduled time, actual send time, and delivery status from the provider's webhooks. Failures are loud. A wedding reminder that silently bounced is the exact failure this product exists to prevent, and an artist who cannot tell whether mail arrived will send her own alongside it, at which point the app has added work rather than removed it.

---

## 16. The audit trail

### 16.1 On the booking [Soon]

A chronological record of everything that happened, visible on the booking:

- Enquiry created, and how it arrived
- Stage changes
- Quote built, quote sent
- Form sent, form returned, form reviewed
- Agreement version generated, sent, viewed, signed
- Invoice raised
- Payments and refunds recorded
- Messages sent, with delivery status
- Notes added
- Price or date changed, with what changed
- Collaborators added or removed, booking transferred

Read-only, never editable, ordered by time. It answers "what did I do about this, and when", which is the question that comes up whenever something goes wrong.

### 16.2 The legal audit trail [Core, when signing exists]

Different thing, higher standard, covered in section 9 of the technical proposal: the exact rendered document bytes and their hash, a hash-chained event log, the full sent, delivered, viewed and signed sequence, IP and user agent, and three separate deliberate acts at signature.

The booking audit trail in 16.1 is for the artist. This one is for a dispute. Keep them separate, because the second one cannot ever be edited or pruned and the first one can.

---

## 17. Navigation

Five destinations maximum on a phone. The revision from your feedback merges bookings and calendar, which frees a slot.

```
┌──────┬──────────┬─────────┬───────────┬──────┐
│ Home │ Bookings │   (+)   │ Enquiries │ More │
└──────┴──────────┴─────────┴───────────┴──────┘
```

**Bookings** contains the calendar. They are two views of the same data and they should not be two tabs.

**Enquiries** takes the freed slot, which I think is right. It is the screen with the most unresolved things on it, it is where new business lives, and giving it a permanent home is what makes an artist log enquiries at all. If it turns out she looks at it once a week, it moves into More and something else comes forward.

**The plus** is the raised circle. It opens a sheet rather than a form, because there are three things to create:

- New enquiry
- New booking
- New contact

**More** holds contacts, settings, my account, help and sign out.

On desktop all of this becomes a sidebar and the constraint disappears.

---

## 18. Home

The summary screen. Order is a design question rather than a logic one, so this lists the blocks rather than fixing their positions.

### 18.1 Attention [Core]

Grouped by whose court the ball is in:

> **3 need you**
> Sarah Bennett has sent her details
> Emma Clarke needs a price
> Lucy Ward's date is not held (provisional, 16 days)
>
> **2 waiting on clients**
> Hannah Price has not signed
> Jo Mitchell's deposit is overdue by 4 days

Each row taps through. **Suppressed by feature toggles**, per section 6: if she does not use invoicing, nothing is ever waiting on a deposit. If the block is empty it disappears rather than showing an empty state.

### 18.2 Next up [Core]

The next two or three events with date, day, time, client, venue and travel time. The "what am I doing on Saturday" answer, readable without scrolling.

### 18.3 Money [Soon]

Period selector: this month, last 3 months, last 12 months, this business year, custom. Business year start date is a setting, because sole traders are often on 6 April.

| Figure | Definition |
|---|---|
| **Received** | Payments received in the period |
| **Outstanding** | Invoiced, due, unpaid. Split into due and overdue |
| **Booked ahead** | Confirmed bookings with future events |
| **Provisional** | Provisional bookings, shown separately, never added to the confirmed figure |

Plus a booking count and an average value, which are the numbers she will want when deciding whether to put her prices up.

**Report on the payment date, not the invoice date**, which matches cash basis accounting and therefore her tax return. Worth confirming with her accountant.

Where this block sits, top or lower down, is worth testing. Revenue at the top is satisfying and it is also the thing that changes least often. Attention at the top is more useful and less pleasant. That is a design decision with a real trade-off in it.

### 18.4 Search [Soon]

One field. Client name, venue, date. Typing "Hedsor" finds every booking there, "June 2027" finds that month. Postgres full-text search will carry this a very long way; a dedicated search service is a problem for a version of the business that does not exist yet.

---

## 19. Bookings, the calendar, and the booking screen

### 19.1 One screen, two views [Core]

**Mobile.** A compact month calendar at the top with marks on days, and the list below it. Tapping a day filters the list to that day. Toggling the calendar away gives the full list.

Day marks carry a count and a colour by the highest state present:

| State | Mark |
|---|---|
| Confirmed | Solid |
| Provisional | Outlined |
| Possible enquiries | Count badge in a distinct colour |

So a day with one confirmed booking and three live enquiries shows both, which is the situation the artist most needs to see at a glance.

**Desktop.** Calendar on the left, list on the right, both live. Clicking either opens the booking.

### 19.2 The list [Core]

Rows: client name, event date and day, venue, total, status pill, waiting-on pill.

Tabs for Upcoming, Past and All, with a status filter. Upcoming groups under sticky headers: this week, this month, next three months, later. Past groups by month, descending. The urgent end is always at the top.

### 19.3 The booking screen [Core]

The most important screen in the product.

**A header that is always visible:** client name, wedding date and day, venue, total, status pill, and the two or three most likely next actions, which change with the stage.

**Then a summary with everything on it**, one line or one small block per area, each expandable. Your instinct was an accordion or a slide-to-detail, and either works. What matters is that **the summary is complete on its own**: dates, venue, party size, price, what has been paid, what is outstanding, what is coming up. She should be able to answer any question about the booking without opening anything, and open a section only when she wants to change something.

The sections:

| Section | Contains |
|---|---|
| **Dates and venue** | Both events, addresses, travel, maps |
| **Party** | Who is having what done |
| **Price** | Lines, discount, expenses, deposit and balance |
| **Payments** | What is paid, what is due, record a payment |
| **Details** | Intake form answers, with the review flow |
| **Agreement** | Current version, status, PDF, version history |
| **Messages** | Upcoming with edit and cancel, sent with status |
| **Photos** | Inspiration, trial, wedding, consent flag, photographer link |
| **Notes** | The note stream, with reminders |
| **Activity** | The audit trail from 16.1 |

**The header and summary are the 5am screen.** They have to work with no signal and without scrolling.

### 19.4 Actions on a booking [Core]

- Add to phone calendar, and remove
- Duplicate, for a second wedding in the same family
- Change date, which moves the events, reschedules pending messages, offers a new agreement version, and updates the calendar entry
- Cancel, which stops pending messages, records a reason and prompts about a refund
- Transfer to another artist, per section 20
- Delete, which is not the same as cancel and should be limited to enquiries that went nowhere

### 19.5 Voice actions on a booking [Idea]

The natural extension of 5.5. Open a booking, tap the microphone, and describe a change: *"Move the trial to the 8th of March, and add two more bridesmaids."*

The value is real, because the alternative is four screens and eleven taps, and because it is usable while walking. But it is an idea rather than a specification until three things are decided:

**A strict allow-list of operations.** Voice can change dates, party, quantities, notes and statuses. Voice does not sign anything, does not send anything to a client, does not record a payment and does not delete. Not because it could not, but because the cost of a misheard instruction is asymmetric: a wrong date is visible and fixable, a contract sent to the wrong person is not.

**It proposes, she confirms.** The app shows exactly what it is about to change, as a before and after, and nothing happens until she taps. This is the same rule as 5.5.1 and for the same reason.

**Every voice change goes in the activity trail** with the transcript attached, so that "why does it say four bridesmaids" has an answer.

Recorded now because it shapes nothing today and costs nothing to leave on the list. It becomes worth building once the booking screen exists and there is evidence about which changes actually get made most often, which is a question worth answering with data rather than guesses.

### 19.6 Reaching a client quickly

Small feature, disproportionate value, and it is almost all free.

**Tap to call and tap to email [Core].** Every phone number and email address anywhere in the app is tappable. On the booking header, in the contacts block, in the party list if a number is ever stored there.

This needs no permissions, no plugin and no native code. A `tel:` link and a `mailto:` link are ordinary HTML, they work identically in the web app and inside the web view, and they work with no signal. It is genuinely a few lines.

**List every number, labelled.** This matters more than it looks, because **the bride is often the worst person to ring on a wedding morning.** She is having her hair done, her phone is in a bag in another room, and somebody else is holding it. The numbers that actually get used are the bridesmaid, the mother, the planner and the venue. The extra contacts from section 4.2 exist for exactly this moment, so the booking should show them as a short labelled list, each one tappable, rather than burying anyone behind the bride.

**Save to phone contacts [Soon].** Your instinct is right that it belongs on the contact rather than the booking, and right that plenty of people will not want it.

There are two ways to build it and the difference is worth knowing before a plugin gets chosen:

| Approach | Permission needed |
|---|---|
| Contacts plugin writing directly to the address book | Full contacts access, usually read and write |
| **Generate a vCard and hand it to the system share sheet** | **None at all** |

**Take the second one.** The app builds a `.vcf` file in memory and passes it to the operating system, which shows its own add-contact screen. The artist sees exactly what is about to be saved, confirms it herself, and the app never asks for access to her address book, never sees who else is in it, and cannot write anything she has not looked at. It also means the operating system handles merging with an existing contact, which is a problem you would otherwise have to solve.

Given that you personally would not grant an app contacts access, this is the version that gets used rather than declined.

**What goes on the card:**

| Field | Value |
|---|---|
| Name | `Sarah Bennett (Wedding)`, with the suffix removable before saving |
| Phone | All numbers held, labelled |
| Email | As held |
| Address | If held |
| URL | A link straight to the booking |
| Note | Wedding date and venue |

The suffix is a good idea. A phone full of client names with no context is a phone where nobody can remember who Hannah is in four years.

**On the URL field.** Use `https://app.klaroly.com/bookings/<uuid>`, never a custom scheme like `klaroly://`. A normal HTTPS link opens the native app directly when universal links on iOS and app links on Android are configured, and falls back to the web app when they are not, which means the link keeps working on her laptop and on a device where the app has been deleted. A custom scheme does neither and looks broken.

It has to be the booking's stable identifier rather than anything containing a name or a date, since both of those change and the contact card will not update itself.

**Two things to say out loud rather than let people discover:**

Anything written to the address book **leaves the app**, which means account deletion, the export in section 26.3 and any future correction never reach it. That is not a problem, it is how saving a contact works, but section 26 makes a careful point about the artist being the controller for her clients' data, and this is one route by which that data ends up somewhere neither of you can reach.

And **deleting a booking does not delete the contact card**. Also correct behaviour, also worth one sentence in the confirm screen rather than a surprise later.

**On the wider point about on-device intelligence.** You are right that a well-populated contact card is more useful than a sparse one, and that assistants on both platforms increasingly work from what is in the address book rather than from what is in any individual app. It costs nothing to fill in every field properly rather than just a name and a number, so do that. I would not build anything specifically for it beyond that, since the shape of those capabilities is still moving and a complete contact card is the whole preparation anyway.

**From the contacts list [Soon].** Tapping a contact shows their bookings, their numbers, tap to call, tap to email, and save to phone. That is the entire contacts screen, and it is the reason the contact is its own record rather than a set of columns on a booking.


---

## 20. Team, assistants and transferring a booking

This is the largest new area in your feedback and the one with the most structural consequence. None of it is early. All of it depends on section 4.5 being right now.

### 20.1 What actually happens in the business

Three real situations, each needing something slightly different:

**An assistant.** A junior or a trainee working the day with her. Needs the address, the call time, the party, the look, the notes. Must not see prices, invoices or payments.

**A second artist.** A peer brought in for a large party, running her own business, with her own account. Needs the same operational detail. Might be quoting her own rate separately, might be paid by the lead artist.

**A handover.** Illness, pregnancy, an accident, a double booking that cannot be resolved. The whole booking moves to another artist, ideally with everything already gathered rather than starting again a fortnight before a wedding.

That third one is the one you have already seen happen, and it is the most valuable of the three because it happens at the worst possible moment.

### 20.2 The shape [Later, on Core foundations]

Your correction simplifies this considerably, and the simpler version is better. There are not two mechanisms, there is one.

**One owner, and collaborators.** The owner holds the paid account. She can invite a number of collaborators, capped by her plan. A collaborator is a person she has given access to one or more bookings. Whether they are an assistant, a trainee or another working artist is a matter of what she switches on for them, not a different kind of record.

```
accounts        the business, owned by one user
account_user    the owner, plus any collaborators, with a permission set
booking_user    which collaborators are on which booking
```

**Read-only is the default**, which is the right default and makes the whole feature much easier to reason about. She then switches things on:

| Toggle | Default | Effect |
|---|---|---|
| Can edit | Off | Off means they can look but change nothing |
| Show prices | Off | Quote, rate card, totals |
| Show invoices and payments | Off | Everything financial |
| Show client contact details | On | Phone and email for the day |

Sections the owner has switched off globally, per section 21.2, stay off for everyone. A collaborator never sees a feature the account does not use.

**What a read-only collaborator gets** is exactly what someone working the day needs: dates, times, both addresses, travel, the party and who is having what, the look, the inspiration images, the notes, and the contact details. That is a genuinely useful thing to hand someone, and it replaces a screenshot and three WhatsApp messages.

**Notifications.** Collaborators are told when a booking they are on changes: a date moves, the party changes, a note is added. That is most of the value of sharing rather than screenshotting, so it is not an optional extra.

**A user can belong to more than one account.** Another working artist will own her own account and appear as a collaborator on somebody else's. The `accounts` and `account_user` shape in section 4.5 already allows that, which is the whole reason for putting it in before it is needed. What the interface has to do is make switching between them obvious and make it impossible to confuse whose booking is on screen.

### 20.2.1 The invite flow [Later]

**If she already has an account:** a push notification and an entry in her bookings list, marked as shared and naming the lead artist. One tap and she is looking at it.

**If she does not:** an email or a link with an invitation. She downloads the app, creates an account, and the shared booking is waiting for her when she lands.

That second path is a signup, and it is worth treating as one of the better ones the product will get. The person arriving has been personally invited by someone she works with, to look at a real booking with real information in it, which is a considerably warmer start than a marketing page. **Do not put a paywall, a trial prompt or a price anywhere in that flow.** She is there to look at Saturday's call time. The pitch, if there is one, comes later and by email.

### 20.3 Transfer [Later]

Distinct from sharing. The booking's owning account changes.

The flow: the original artist picks transfer, names the new artist by email, and the new artist accepts. Everything moves: events, party, price, agreement history, messages, photos, notes, audit trail. An optional email tells the bride, which is a decision the artist makes rather than the app.

Two things to decide when it is built:

- **Does the original artist keep read access?** I would say yes by default, as a collaborator without financial detail, because she may still be answering questions about it. Removable.
- **What happens to money already taken?** The deposit went to the original artist's bank. That is a conversation between two humans and the app should not pretend otherwise, but the booking should record clearly what was paid and to whom, and the new artist's invoice should start from the right number.

### 20.4 What this means for pricing the product [Idea]

If a second artist has to create an account to accept a shared booking, then every shared booking is a signup. That is a genuinely good acquisition channel and it is worth designing for rather than tolerating: the invite should be a pleasant first experience, the shared booking should be useful on its own, and there should be an obvious path from "I was invited to one booking" to "I run my own bookings here".

Worth noting now because it affects whether a collaborator seat is free. It should be, entirely. A seat cap on the owner's plan is the right lever, because it limits cost without ever charging the person you are trying to recruit.

---

## 21. Feature toggles, plans and entitlement

Your feedback merged three things that look the same and behave very differently. Keeping them apart in the code is worth a paragraph now, because conflating them produces the kind of mess that is miserable to untangle later.

### 21.1 Three different questions

| Question | Answered by | Example |
|---|---|---|
| **Does she want this?** | A feature toggle, her preference | "I never send contracts" |
| **Is she allowed this?** | Entitlement, her plan | "Automation is on the paid plan" |
| **Does this booking use it?** | A per-booking override | "This one is a favour, no invoice" |

A feature is available when **entitlement allows it and she has switched it on**, and it applies to a booking unless that booking says otherwise. Three separate checks, one helper function, and nothing anywhere else asks the question directly. The technical proposal already establishes this pattern for subscriptions with `hasActiveEntitlement()`, and this is the same idea widened.

### 21.2 The toggles [Core]

Global, in settings, each also overridable per booking:

- Enquiries
- Intake forms
- Agreements
- Invoicing
- Payment tracking
- Email automation, and each template individually
- Travel estimates
- Photos
- Feedback requests

Switching one off does three things: it hides the section on the booking screen, it stops the related scheduled messages, and it removes the related items from the home screen's attention block. That last one is what makes the toggles worth having, because an artist who does not use contracts should never be told that someone has not signed one.

**Default new accounts to almost everything off.** Bookings, enquiries, dates, party, price. That is a coherent, useful product on its own, it is instantly understandable, and every feature she switches on afterwards is one she went looking for. The opposite default, everything on, presents a first-run experience of eleven things she does not understand and an attention list full of demands.

### 21.3 Plans and pricing [Idea]

Your thinking here is sound and the shape is right, so this section records it and flags the mechanics rather than arguing with it.

**Early access pricing, locked.** Everyone who signs up while the product is being built pays a low rate, around £15 a month or £90 to £100 for a year, and keeps it. Later, once there is more in the box, new customers pay more, perhaps £20 to £25 a month or £150 a year.

Three things this gets right. It creates a real reason to sign up now rather than later, which is the hardest thing to manufacture for a new product. It rewards the people taking a risk on unfinished software, which is the correct instinct and also the reason they will tolerate the rough edges. And it means the price rise is a piece of good news for existing customers rather than a bad one, which is unusual and worth having.

**Four mechanics to get right, because grandfathering is easy to promise and fiddly to honour:**

1. **A price is a record, not a setting.** Every subscription points at the plan and price it was sold on. Raising prices means creating a new price and pointing new signups at it, never editing the old one. Stripe models this properly with separate price objects on a product, and Cashier follows it. The failure mode is an artist logging in one morning to a number she was promised would not change.
2. **Say what "locked" means, in writing, before anyone pays.** For as long as the subscription is continuous, or forever including after a lapse and a resubscribe? These are different promises and only one of them is cheap. My suggestion is: locked for as long as it runs unbroken, and a lapse of more than a month returns to current pricing. Say so on the pricing page.
3. **Annual and monthly are two prices, not one price times twelve.** Annual up front is worth a real discount because it is cash now and a year of retention. £15 a month against £90 a year is a fifty per cent discount, which is generous; £120 to £150 is the more usual shape. Worth deciding deliberately rather than picking a round number.
4. **Decide gross or net of VAT now.** Still open from the technical proposal. A plan that becomes £18 at checkout later is a conversion problem, and it is much harder to fix after people have signed up on the old understanding.

**On tiering.** The earlier version of this document argued for one plan and a thirty-day trial rather than a free tier, and that argument still holds, for a specific reason: **the automation is both the reason to pay and the thing she has to trust before she will pay.** An artist who has never watched it send four emails correctly has no reason to upgrade to it. Early access pricing does the same job as a free tier, better, because it gets money in and tells you whether anyone values this at all.

The natural first thing to gate later is **AI intake**, per 5.5.2, because its cost genuinely scales with use rather than being fixed. Collaborator seats are the natural second, as a cap rather than a charge, per 20.4.

**What to ask rather than guess.** "What would you pay for this?" produces unreliable answers, because people are being polite and are estimating a feeling. What they already pay, and what they have cancelled, are facts. Section 31 asks it that way.


---

## 22. Device calendar integration

You said you have not done this before, so here is what it involves.

### 22.1 What it actually is [Soon]

The phone's calendar is a database the operating system exposes through an API. With permission, an app can create calendars in it, and create, update and delete events. Anything written appears in the artist's normal calendar app alongside everything else, and syncs to her other devices through whatever account she uses. The technical proposal already picks the plugin.

### 22.2 How it should behave

**Write to a dedicated calendar the app creates**, named after the app, rather than into her default. She can then hide it, colour it separately, and nothing the app does ever touches a personal event. This is the single most important choice here and it is easy to get wrong.

**Store the created event's identifier on our event record.** Without it, changing a date creates a duplicate rather than moving anything, and deleting a booking leaves an orphan in her calendar forever.

**Confirmed bookings only, and only if she opts in.** Enquiries and provisional bookings stay inside the app, per your instinct. An in-app calendar that shows soft holds and a device calendar that shows only real commitments is the right division, and it means her personal calendar never fills up with maybes.

**Put the venue address in the location field.** Her phone then does travel-time alerts, "time to leave" notifications and one-tap directions for free, using live traffic, with no work from us. This is worth more than any estimate we build, and it is one field.

**Ask for permission at the moment she first taps add**, with a sentence explaining why, not on first launch. Permission requested in context is granted far more often.

### 22.3 The alternative [Idea]

An **iCal subscription feed**: a URL she subscribes to once, which works on desktop too and needs no permissions at all. The technical proposal already lists the library.

The trade-off is that a subscribed feed is read-only and refreshes on the phone's own schedule, which can be several hours. For weddings booked a year out that is fine; for a change made the night before it is not.

**Do the native write for confirmed bookings, offer the feed as a settings extra.** They are not mutually exclusive and the feed costs almost nothing once the data exists.

---

## 23. Offline behaviour and the day before

Covered in section 8 of the technical proposal. Two additions from your feedback.

### 23.1 The day-before sync [Soon]

A local notification the day before an event: *"Sarah's wedding is tomorrow. Open the app to make sure you have everything."* Tapping it opens the booking and forces a full sync of that booking, including full-resolution inspiration images.

Better still, do it automatically. The app already knows the date, so a background refresh of tomorrow's bookings whenever it is opened on the preceding day costs nothing and means the notification is a reassurance rather than an instruction.

The failure this prevents is specific and bad: arriving at a venue with no signal, opening the app, and finding a booking that was updated last week has not been fetched.

### 23.2 What has to work with no signal [Core]

The booking header and summary, both addresses, call times, the party, the notes, and the inspiration images. Everything else can wait for a connection.

---

## 24. Settings

The artist's defaults. Everything here is global, and almost everything is overridable per booking.

| Group | Contains |
|---|---|
| **Features** | The toggles from 21.2 |
| **Rate card** | Services, prices, band labels and definitions, expenses |
| **Travel** | Base postcode, charging rule, per-mile rate |
| **Deposit and payment** | Deposit rule, due dates, payment instructions, invoice numbering, business details |
| **Email templates** | Every template, editable, switchable, resettable |
| **Automation** | Which reminders run, intervals, chasing limits |
| **Agreement** | Wording, cancellation terms, reset to standard |
| **Enquiry and intake form** | Sections on or off, custom questions, the public URL |
| **Working details** | Default working hours, early start threshold |
| **Business year** | Start date for reporting |

---

## 25. My Account

About the person rather than the business:

- Profile photo, name, business name
- Email address, password, which sign-in methods are attached
- Subscription and billing status, **absent entirely from the mobile build**, per the technical proposal
- Notification preferences, per type
- Face ID or Touch ID app lock, with a timeout
- Pause account
- Delete account

---

## 26. Data, deletion and record keeping

I need to correct something in your feedback, because you asked to be corrected and this one matters.

### 26.1 You will hold financial records, whether or not you want to

> "I will never keep financial data. All the payments they pay to use the service will be on Stripe anyway, and Stripe complies with all the financial details."

Two separate things are being run together here.

**Stripe holds card data and the records of transactions that went through Stripe.** That is true, it is the reason to use Stripe, and it means you never touch a card number. Correct on that.

But **the invoices and payments in this app are not Stripe's records. They are the artist's business records, and they live in your database.** When she records that Sarah paid a £200 deposit by bank transfer on 14 March, Stripe has never heard of it. That row exists in one place only: your Postgres instance. The same is true of every invoice she raises, every quote, every agreed price, and every refund she notes down.

So the product is holding financial records for the artist's business from the first booking, regardless of whether card payments are ever added. That has three consequences:

**She is relying on you for records she may need for six years.** If her account is deleted and those rows go, she has lost her own invoice history. That is the strongest argument in the document for the export in 26.3.

**Deletion is not simply "remove everything".** The right shape is still pseudonymising personal data while retaining what is needed, but the reason is not your obligation, it is hers.

**Her subscription payments to you** are a different thing again, and those genuinely are Stripe's problem plus Cashier's local record. That part of your instinct was right, it just does not cover the larger case.

### 26.2 Controller and processor

Worth restating because it has not yet landed anywhere in the planning.

**The artist is the data controller for her clients' data. The platform is her processor.** She decides what is collected and why. You process it on her instructions.

That means the terms need a **data processing agreement** between you and each artist, the privacy policy has to describe both relationships, and when a bride asks what is held about her, the artist answers and you help her answer. It also means you should not be making unilateral decisions about her clients' records.

None of this blocks development. It blocks taking a second customer, so it wants doing before launch rather than after.

### 26.3 Deletion, pausing and export

**Export first, and make it good.** Every booking, contact, agreement, invoice, payment, note and photo, as a downloadable archive. It is the decent thing to do, it is required in substance by data protection law, and it turns account deletion from a crisis into a transaction.

**Delete account** pseudonymises the artist's personal data and her clients', retains what has to be retained, and revokes access. Presented honestly, with the export offered first. Available in the app on both stores plus a public web URL for Google Play.

**Pause account** is a subscription state, not a data state. Everything stays, the app goes read-only, automation stops. This is the right offer for a seasonal business and it will save subscriptions that would otherwise cancel in January.

### 26.4 Contracts

Your instinct is right that the emailed copies to both parties are the primary record, and that reduces the retention pressure considerably.

One caveat. If the audit trail is sold as a feature, meaning "this is provable", then deleting it removes the thing that made it provable. The signed PDF in an inbox is evidence of a signature; the hash-chained log is evidence of the circumstances. If a dispute arises three years later, the second one is what actually settles it.

**Six years is the number** that matches both the limitation period for a simple contract in England and the financial retention requirement, so retaining signed agreements and their audit trails for six years after the wedding date is a defensible, consistent position. Confirm it with the same solicitor as the cancellation clause, since they are one conversation.

---

## 27. Notifications

Local where possible, push where necessary, per the technical proposal.

| Notification | Type |
|---|---|
| Your 6am call is tomorrow | Local |
| You have a booking in an hour | Local |
| Your note reminder | Local |
| Travel disruption on your route | Push, later |
| Sarah signed her contract | Push |
| Sarah's deposit has arrived | Push |
| New enquiry from your website | Push |
| Sarah has sent her details | Push |
| A booking has been shared with you | Push |

Every one switchable in My Account. Wedding-day notifications use the iOS **time sensitive** interruption level, since call times are often before dawn when phones are in a sleep Focus.

---

## 28. Where I still think there is a problem

Four things. The first two are new since version 0.1.

**1. The enquiry feature is the product, and it is not the one you set out to build.** Reading your feedback back, the enquiries section with soft calendar holds and clash warnings is the only part that is useful on the very first day, with no setup, no templates, no contracts and no rate card. It solves a problem the artist has today and cannot solve with a notes app: *which of the eleven people I am talking to wants the 12th of June, and which of them have I not replied to.* Everything else in this document improves a booking that already exists. That one wins a booking. Worth weighing when you prioritise, because it is also the cheapest thing here.

**2. There is still no quote object, and section 7.3 half-invented one.** A quote that can be copied, sent or downloaded is a thing with a state: drafted, sent, accepted, declined, expired. Without that state the artist cannot see what she is waiting on, and you cannot report on conversion or on what price loses. I would model **quote as a record with a sent date and an outcome**, not as an email that happens to contain numbers. It is a small table and it makes the whole enquiry funnel measurable.

**3. The email templates are the product, and writing them is not engineering.** Fifteen templates that sound like a person, in her voice, that work whether she is going to the bride or the bride is coming to her. Whether an artist trusts the app to email her clients is decided entirely by whether those emails read well. **Write four of them properly and show them to Jess before building any scheduling at all.** The scheduler is a weekend. The wording is the risk.

**4. Two questions still block launch rather than development.** The solicitor question on consumer cancellation rights and deposits, and the controller and processor position with a data processing agreement. Neither stops you writing code next week. Both stop you taking a second customer.

---

## 29. If you asked me what to build first

You did not, and prioritising is Jess's job before it is mine. But since the register below is long enough to be daunting, here is the shape I would argue for, purely as something to react to.

**A booking list, a calendar, enquiries with soft holds, and clash warnings.** No contracts, no invoices, no automation, no forms. Add a party and a price because they are cheap and they make a booking feel real.

That is a coherent product. It is the thing an artist can start using on a Tuesday with no setup. And crucially, **it is the version that tells you whether she opens the app every day**, which is the only question worth answering before building the other twenty features.

If she opens it daily, everything else in this document is worth building. If she does not, no amount of contract signing will save it.

**The one thing I would add to that list**, despite it being marked Soon rather than Core, is on-the-spot capture from section 5.6. It is a few hours of work on top of an enquiries feature that already exists, it is the only thing in the document that earns its keep on a working Saturday rather than on an admin evening, and it is the only one that might bring in a booking that would otherwise never have happened. Everything else here saves time. That one makes money.

---

## 30. Feature register

Everything in one table. Sort it, cut it, move rows after the artist conversations.

### Foundations, structural

| # | Feature | Horizon | Notes |
|---|---|---|---|
| F1 | Events table, not date columns | Core | 4.1 |
| F2 | Contact as its own record | Core | 4.2 |
| F3 | Party members by service, no ages | Core | 4.2 |
| F4 | One bookings table, enquiry as a stage | Core | 4.3 |
| F5 | Account ID on every table | Core | 4.5. Cannot be retrofitted cheaply |
| F6 | Price snapshots on quote lines | Core | 4.6 |
| F7 | Agreement versions table | Core | 10.2. Table now, feature later |
| F8 | Entitlement helper, one function | Core | 21.1 |
| F9 | Timestamps with time zone throughout | Core | 4.1 |

### Enquiries

| # | Feature | Horizon | Notes |
|---|---|---|---|
| E1 | Enquiry stages, one-tap progression | Core | 5.1 |
| E2 | Soft calendar holds at Possible | Core | 5.2 |
| E3 | Clash warnings across bookings and enquiries | Core | 5.2 |
| E4 | Last-touched age shown in the warning | Core | 5.2 |
| E5 | Convert to booking, reversible | Core | 5.3 |
| E6 | Lost reasons and conversion reporting | Soon | |
| E7 | Hosted enquiry form on a public URL | Soon | 5.4 |
| E8 | Embeddable form for her own site | Later | 5.4 |
| E9 | AI intake, paste a conversation | Later | 5.5 |
| E10 | AI intake, private forwarding address | Later | 5.5 |
| E11 | AI intake, voice note | Later | 5.5. The route to build first |
| E12 | On-the-spot capture at a venue | Soon | 5.6 |
| E13 | Instant introduction email plus one chase | Soon | 5.6 |
| E14 | Hand-the-phone capture mode, biometrically locked | Soon | 5.6.1 |
| E15 | Follow-up reminder to the artist, not just the guest | Soon | 5.6.4 |
| E16 | Enquiry source, including which booking it came from | Soon | 5.6.5 |
| E17 | Reporting: which venues and weddings generate work | Later | 5.6.5 |

### Bookings and pricing

| # | Feature | Horizon | Notes |
|---|---|---|---|
| B1 | Booking with two events, party, venue | Core | |
| B2 | Rate card with editable services | Core | 7.1 |
| B3 | Tap-to-build quote from the rate card | Core | 7.2 |
| B4 | Fixed price mode | Core | 7.2 |
| B5 | Expenses: accommodation, parking, other | Core | 7.1 |
| B6 | Quote as a record with an outcome | Soon | 28, point 2 |
| B7 | Copy, send or download a quote | Soon | 7.3 |
| B8 | Deposit and balance rules with overrides | Soon | 7.4 |
| B9 | Duplicate a booking | Soon | 19.4 |
| B10 | Change date, with knock-on effects | Soon | 19.4 |
| B11 | Cancel with reason and refund prompt | Soon | 19.4 |

### Travel and venue

| # | Feature | Horizon | Notes |
|---|---|---|---|
| T1 | Open in Maps, venue search link | Core | 8.4 |
| T2 | Driving distance and time, stored once | Soon | 8.1 |
| T3 | Travel charging rules | Soon | 8.2 |
| T4 | Her own venue notes, building up over time | Idea | 8.4 |
| T5 | Travel disruption alerts before an event | Idea | 8.3 |

### Documents and money

| # | Feature | Horizon | Notes |
|---|---|---|---|
| D1 | Invoice generation and numbering | Soon | 11.1 |
| D2 | Payment records, modal entry | Soon | 11.2 |
| D3 | Part payments, overpayments, refunds | Soon | 11.2 |
| D4 | Payment chasers with snooze | Soon | 11.3 |
| D5 | Payment instructions as a free field | Soon | 11.1 |
| D6 | Invoice PDF, attach or download | Soon | 11.1 |
| D7 | Intake form, toggleable sections | Later | 9 |
| D8 | Custom questions, five of them | Later | 9.1 |
| D9 | Agreement generation and signing | Later | 10.1 |
| D10 | Legal audit trail, hash chained | Later | 16.2 |
| D11 | Stripe Connect for client card payments | Later | 11.4 |

### Messages

| # | Feature | Horizon | Notes |
|---|---|---|---|
| M1 | Template library with good defaults | Core | 15.1 |
| M2 | Copy to clipboard on every template | Core | 15.2 |
| M3 | Global and per-booking overrides | Soon | 15.1 |
| M4 | Scheduled messages, editable before sending | Soon | 15.3 |
| M5 | Location-aware variants | Soon | 15.5 |
| M6 | Delivery status from webhooks | Soon | 15.8 |
| M7 | Regenerate from template | Soon | 15.3 |
| M8 | Inbound reply capture | Later | 15.7 |
| M9 | Introduction email from an on-the-spot capture | Soon | 5.6 |
| M10 | SMS, for on-the-spot introductions only | Idea | 5.6 |

### After the day

| # | Feature | Horizon | Notes |
|---|---|---|---|
| A1 | Photos labelled inspiration, trial, wedding | Soon | 13.2 |
| A2 | Photo consent flag, blocking export | Soon | 13.3 |
| A3 | Photographer gallery link field | Soon | 13.4 |
| A4 | Private feedback logged on the booking | Later | 12.1 |
| A5 | Public profile with verified reviews | Idea | 12.2. A different business |

### Team

| # | Feature | Horizon | Notes |
|---|---|---|---|
| C1 | Collaborators on a booking, read-only by default | Later | 20.2 |
| C2 | Permission toggles: edit, prices, invoices, contacts | Later | 20.2 |
| C3 | Collaborator notifications when a booking changes | Later | 20.2 |
| C4 | Invite flow, with signup for new users | Later | 20.2.1 |
| C5 | A user belonging to more than one account | Later | 20.2 |
| C6 | Transfer a booking outright | Later | 20.3 |
| C7 | Free collaborator seats, capped by plan | Idea | 20.4 |

### Client access and portal

Added 2 September 2026. Technical proposal section 9 has the shape.

| # | Feature | Horizon | Notes |
|---|---|---|---|
| L1 | Optional client login, offered after signing from the link | Later | Links stay primary. Never required to sign |
| L2 | One `users` table: a person is a user, client and artist are relationships | Core, as a rule | An artist who books a photographer on Klaroly is both, with one login. Nullable `contacts.user_id` in the first migrations; `users` carries only person things |
| L3 | Contact linked to a login from the token, never by email matching | Later | Apple private relay makes matching unreliable |
| L4 | Portal on one host (`my.klaroly.com`), read-only, artist controls what is shown | Later | Intake answers, agreement PDF, invoice |
| L5 | One login screen with `return_to`, routing by what the person is | Later | No `auth.` host. The shared cookie already covers the web |
| L6 | Reserved usernames: `auth`, `my`, `portal`, `client`, `clients` | Core | Before the first signup |
| L7 | Optional per-booking PIN on every client link, encrypted, rate limited, change and revoke as separate actions | Core, as columns | Decision 72. Sent separately from the link, never in the same email |

### App and platform

| # | Feature | Horizon | Notes |
|---|---|---|---|
| P1 | Home: attention, next up | Core | 18.1, 18.2 |
| P2 | Bookings with calendar and list views | Core | 19.1 |
| P3 | Booking screen with expandable summary | Core | 19.3 |
| P4 | Note stream, dated | Core | 14.1 |
| P5 | Feature toggles, global and per booking | Core | 21.2 |
| P6 | Home: money summary | Soon | 18.3 |
| P7 | Search across name, venue, date | Soon | 18.4 |
| P8 | Booking activity trail | Soon | 16.1 |
| P9 | Note reminders as local notifications | Soon | 14.2 |
| P10 | Device calendar write for confirmed bookings | Soon | 22 |
| P11 | Day-before sync and prompt | Soon | 23.1 |
| P12 | Offline read cache | Soon | 23.2 |
| P13 | Face ID app lock | Soon | 25 |
| P14 | Full data export | Soon | 26.3 |
| P15 | Pause account | Soon | 26.3 |
| P16 | Account deletion with retention rules | Soon | 26.3 |
| P17 | iCal subscription feed | Idea | 22.3 |
| P18 | Username claimed at signup, with a reserved list | Core | 4.7 |
| P19 | Username history, permanent, with redirects | Core | 4.7 |
| P20 | Pretty client URLs, token still the security | Soon | 4.8 |
| P21 | Public profile at `www.klaroly.com/username` | Idea | 4.8, 12.2 |
| P22 | Voice actions on a booking | Idea | 19.5 |
| P23 | Tap to call and tap to email, every number labelled | Core | 19.6 |
| P24 | Save to phone contacts by vCard, no permissions | Soon | 19.6 |
| P25 | Contact card carries a deep link to the booking | Soon | 19.6 |
| P26 | Contacts screen: bookings, numbers, save to phone | Soon | 19.6 |

---

## 31. Questions for the artists

Written to be asked as they are, to Jess and to the others independently. The ones nobody has a view on are the ones to cut from the product.

**How work arrives**

1. How does a wedding enquiry usually reach you: website, Instagram, email, word of mouth, a recommendation from a venue?
2. Roughly how many enquiries turn into bookings? What usually loses the ones that do not?
3. How far ahead do people book? What is the longest you have held a date?
4. **How do you keep track of who has asked about which date?** What happens when two people want the same Saturday?
5. Do you ever hold a date provisionally? For how long, and how do you remember to chase it?
6. How many conversations are you having at once in a busy month?

**Trials**

7. Is a trial always the bride only, or sometimes the mother or a bridesmaid too?
8. Charged separately, or folded into the bride's price?
9. At your place or theirs, and who decides?
10. How often does someone want a second trial?

**Money**

11. What are your current prices for a bride, a bridesmaid, a mother, a senior, a child, a gentleman?
12. Do you charge for travel? How do you work it out?
13. Do you ever stay overnight? Who pays for the hotel, and how do you present that?
14. Do you charge extra for very early starts?
15. What deposit do you take, and when is the balance due?
16. Is the deposit refundable? What happens if they cancel three months out? Three weeks out?
17. How do people pay: transfer, cash, card?
18. Have you ever been paid late? What did you do?
19. **How do you send a price at the moment?** Show me the last one you sent.

**Contracts**

20. Do you use a contract now? Can I see it?
21. Has anyone ever refused to sign one?
22. What has gone wrong on a booking that you wish had been written down?

**The day itself**

23. What do you need to know at 6am that you cannot get from your phone easily today?
24. Do you take photos of your work? Do you ask permission to use them?
25. **Do you ever get the photographer's pictures afterwards?** How?
26. Do you record anything about skin type, allergies or products used? Where does that live now?

**Working with other people**

27. Do you ever work with an assistant or a second artist? How do you get them the details?
28. Have you ever had to hand a booking to someone else? What happened?

**Communication**

29. What do you send a bride between booking and the wedding? Walk me through a real one.
30. What do you find yourself typing out again and again?
31. **Would you be happy for an app to email your clients without you seeing it first?** Or would you rather it wrote the email and let you send it?
32. How many emails is too many, from a bride's point of view?
33. Do you use WhatsApp with brides? Is email even the right channel?

**The app**

34. If this did exactly one thing for you, what would it be?
35. What do you use now: paper diary, notes app, spreadsheet, another app?
36. What have you tried and stopped using, and why?
37. Would you rather do this on your phone or sitting at a computer? For which bits?
38. Would you use it while driving or walking, if you could just talk to it?
39. **Has anyone ever asked for your details while you were working?** What did you do?

**What it is worth**

40. What do you already pay for, each month, for the business? Everything: software, insurance, a website, a booking system.
41. What is the most useful of those, and what would you cancel first?
42. Have you ever paid for something like this and stopped? What made you stop?
43. Would you rather pay monthly or once a year?
44. If something saved you an afternoon a week, where would that sit on that list?
45. **On a wedding morning, whose number do you actually ring?** Is it ever the bride?
46. **Would you hand your phone to a guest to type their own details in?** Or does that feel wrong?

Questions 4, 19, 31, 36, 39, 40 and 45 are the ones I would care most about.

Question 4 tells you whether the enquiry feature is real. Question 19 tells you what the quote should look like. Question 31 decides how much of the automation is worth building. Question 36 will tell you more than any of the others. Question 39 tells you whether section 5.6 is a feature or a fantasy. Question 45 decides whose numbers belong at the top of the booking screen, which is a small thing that will matter enormously at 6am. Question 46 is worth asking exactly as written and then watching her face, because handing over an unlocked phone is a thing people either do without thinking or will not do at all.

**Question 40 is deliberately not "what would you pay for this".** That question gets a polite number that means nothing, because the person is guessing at a feeling about a product they have not used. What she already pays for is a fact, what she would cancel first is a ranking, and where this would sit on that list is the closest you can get to a real answer before anyone has been asked for money.

---

## 32. Open decisions for you

Things this document has not settled.

1. **What exactly does "locked" mean on early access pricing?** Section 21.3. Locked while the subscription runs unbroken is cheap and defensible. Locked forever including after a lapse is a promise worth making deliberately, not by accident. It needs to be on the pricing page before the first person pays.
2. **The public profile sits at `www.klaroly.com/username`.** Section 4.8. Settled, and a path rather than a subdomain so that search authority consolidates on the one domain you want ranking. What remains open is that it creates a dependency between the marketing repository and the API that does not exist today.
3. **Does a quote exist as a record with a state?** Section 28, point 2. I think it has to, and it is small.
4. **Where does Money sit on the home screen?** Section 18.3. Satisfying at the top, more useful lower down.
5. **Enquiries as a bottom-bar tab, or inside Bookings?** Section 17. I have put it in the bar, on the argument that visibility is what makes her log enquiries at all.
6. **Is the public review profile a direction you want?** Section 12.2. It is a different business, and the answer changes what gets built years earlier.
7. **Does AI intake get gated to a paid plan?** Section 5.5.2. Its cost scales with use rather than revenue.
8. **Is SMS worth reopening for on-the-spot introductions only?** Section 5.6. It is ruled out for reminders and correctly so, but a handful of introductions a month is a different sum entirely.
9. **Is £15 gross or net of VAT?** Still open from the technical proposal, and 21.3 makes it more pressing, since early access pricing is a promise about a number.
10. **The solicitor question**, on consumer cancellation rights and deposits. Still open, still blocking launch.

---

## 33. What next

1. Take section 31 to Jess, and separately to two or three other artists. Record it if they will let you. Do not show them this document, ask them about their work
2. Write four email templates in a real artist's voice and show them to Jess before building anything that sends
3. Move rows in the register in section 30 against what you hear
4. Rewrite this document against that, and mark what nobody cared about
5. Draw the booking screen and the enquiries screen, because everything else is navigation to them
6. Then schema, then API, per phase 1 of the technical proposal

The accounts on the critical path, the D-U-N-S number and the Apple organisation enrolment, depend on none of this and take four to six weeks. Start those now.
