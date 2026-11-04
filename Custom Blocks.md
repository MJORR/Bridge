# Custom Blocks

The Bridge theme registers these blocks. They live in `src/blocks/<slug>/`. Settings are listed under the sidebar panel that holds them, with the default value in brackets.

**Width** shows up on most blocks. It controls how wide the content column is inside the band: *Wide*, *Narrow* and sometimes *Full window*.

**Mask shape** is a shared panel (`src/editor/band-mask.js`) that several blocks use. It has these settings:
- Show the mask shape [off]
- Mask shade [-20]
- Mask size [60]
- Held against: Left / Right [Right]
- Run past the edge (percent) [0]

Blocks marked *(child)* can only be added inside their parent.

---

## Layout

### Section — `bridge/section`
A band that spans the full window width, with its content held in a column. Any blocks can go inside it.
- **Layout:** Width: Narrow / Wide [Narrow]
- When a full-width block is the last block on a page, it sits directly on the footer with no gap.

### Split Content — `bridge/split-content`
Two columns side by side. Each column is a Group block.
- **Layout:**
  - Width: Wide / Narrow [Wide]
  - Column balance: Even / Wider left / Wider right [Even]

### Alternating Content — `bridge/alternating-content`
Rows of media and text. The image swaps sides on each row down the page.
- **Layout:**
  - Width: Wide / Narrow / Full window [Wide]
  - First row: media on the right [off]

#### Row — `bridge/alternating-row` *(child)*
- **Media:**
  - Media type: Image / Video file / YouTube [Image]
  - YouTube video ID
  - Alt text
  - Cut the corner [off]
  - Cut out with the mask shape [off]
  - Mask size (percent) [100]
  - Image fit: Cover (16:9) / Fit whole image / Fit, with padding [Cover]
- The text side holds ordinary blocks.

---

## Heroes

### Hero Banner — `bridge/hero-banner`
A single hero with a heading, a paragraph and buttons. A Buttons row that is blank or empty is removed in the editor and is not output on the front end.
- **Background:**
  - Style: Image / Solid colour / Gradient / Custom curve / Custom gradient / Two columns [Image]. *Two columns* falls back to *Solid colour* when no image is set, and only shows as two columns at 1024px and wider.
  - Title block on the left [off]
  - Image, Image fit: Cover / Fit whole image [Cover]
  - Focal point
  - Darken image [50]
  - Hold image still (fixed background) [off]
  - Colour [primary]
  - Reframe picture (toolbar button)
- **Gradient** (Custom gradient style):
  - Angle [90]
  - Start point, End point
  - Start colour, End colour
  - Start opacity, End opacity
  - You can also drag the handles on the canvas.
- **Curve** (Custom curve style):
  - Horizontal [44]
  - Vertical [0]
  - Bulge [29]
  - Gap [9]
  - Opacity [35]
  - Text padding [4]
- **Curve on mobile:**
  - Breakpoint (px) [1024]
  - Stack position [62]
  - Horizontal alignment [0]
  - Angle [0]
  - Radius [8]
  - Gap [3]
  - Opacity [60]
  - Horizontal padding [4]
  - Vertical padding (px) [16]
- **Height:**
  - Minimum height: Full screen / Tall (80%) / Medium (60%) / Custom [Full screen]
  - Custom height [80]
  - Unit: vh / dvh / px [vh]

### Hero Slider — `bridge/hero-slider`
A full-screen hero. One image shows as a plain hero. Adding a second slide turns on the arrows and dots.
- **Slider settings:**
  - Full window width [on]
  - Slider height: Full screen / Tall (80%) / Medium (60%) / Custom [Full screen]
  - Custom height [80], Unit: vh / dvh / px [vh]
  - Transition effect: Fade / Slide [Fade]
  - Autoplay [on]
  - Autoplay delay (seconds) [6]
  - Loop slides [on]
  - Show pagination dots [on]
  - Show prev/next arrows [on]
- **Toolbar:** Add slide

### Page Title — `bridge/page-title`
The page title, with an optional banner. Banner style, alignment and background colour are set per page in the **Title Banner** panel of the document sidebar, not on the block.

---

## Content grids

### Cards (Grid) — `bridge/cards`
A responsive grid or list of post cards.
- **Query:**
  - Posts to show: Chosen here / This page's own posts [Chosen here]
  - Post type [post]
  - Number of posts [6]
  - Order by: Date / Title / Modified date / Random / Custom order [Date]. *Custom order* uses each post's Order value. It only appears for post types that have no archive and do support page attributes, such as Projects.
  - Order: Descending / Ascending [Descending]
  - Filter by categories
- **Layout:**
  - Arrangement: Grid / List [Grid]
  - Width: Wide / Narrow [Wide]
  - Card style: Summary / Tile / Cover / Team [Summary]
  - Columns per row [3]
  - Overflow style: Rows / Carousel [Rows]
  - *List only:*
    - Image size: Small / Medium / Large (half) [Medium]
    - Round the image corners [on]
    - Shadow under the image [off]
    - Alternate the sides [off]
    - Shade alternate rows [off]. With *Alternate the sides* on, even rows are shaded; otherwise odd rows are.
    - Shading strength (percent) [12]
- **Card content:**
  - Excerpt length (words) [0]
  - Show read more link [on]
  - Read more text
  - Show link as: Text link / Button / Outline button
  - Button text
- **Mask shape** (shared panel)

### Feature Blocks — `bridge/feature-blocks`
A grid of feature panels, or a numbered run of steps.
- **Layout:**
  - Arrangement: Grid of panels / Numbered steps [Grid]
  - Width: Wide / Narrow [Wide]
  - Columns [3]
  - When they do not fit: Wrap onto new lines / Swipe sideways [Wrap]
  - Space between panels [on]
- **Panels:**
  - Panel style: Card / Plain / Outlined [Card]
  - Text alignment: Left / Centre [Left]
- **Mask shape** (shared panel)

#### Feature — `bridge/feature-block` *(child)*
Prefix, title, text, graphic and background photo are edited on the canvas.
- **Panel:** Pick this one out (highlight) [off]
- **Above the title:** Prefix colour
- **Over the photograph:**
  - Wash colour
  - Wash strength [65]
- **Link:**
  - Link URL
  - Link text
  - Show it as: Button / Text link [Button]

### Price Table — `bridge/price-table`
- **Layout:**
  - Width: Wide / Narrow [Wide]
  - Columns [3]

#### Price Card — `bridge/price-card` *(child)*
Title, price, description and image are edited on the canvas.
- **Card:** Highlight this card [off]

### Testimonials — `bridge/testimonials`
Customer testimonials in a grid or a swipeable row.
- **Layout:**
  - Width: Wide / Narrow [Narrow]
  - Columns [3]
  - When they do not fit: Wrap onto new lines / Swipe sideways [Wrap]
  - Card style: Solid / Outlined [Solid]

#### Testimonial — `bridge/testimonial` *(child)*
Title, text, name and company are edited on the canvas.
- **Rating:** Stars [0]

### Goals — `bridge/goals`
A row of figures that count up as the band scrolls into view.
- **Layout:**
  - Width: Wide / Narrow [Wide]
  - Columns: Three / Four [Three]
  - Text alignment: Centre / Left [Centre]
- **Animation:** Count up on scroll [on]

#### Goal — `bridge/goal` *(child)*
Number, unit and label, all edited on the canvas.

### Gallery — `bridge/gallery`
- **Layout:**
  - Width: Wide / Narrow [Wide]
  - Columns [3]
  - Open images full size (lightbox) [on]

#### Gallery Image — `bridge/gallery-item` *(child)*
- **Image:** Alt text
- **Link:** Link URL
- The label is edited on the canvas.

### Downloads — `bridge/downloads`
- **Layout:**
  - Width: Wide / Narrow [Wide]
  - Card style: Summary / Tile [Summary]
  - Columns [3]
- **Mask shape** (shared panel)

#### Download — `bridge/download-item` *(child)*
Title and link text are edited on the canvas.
- **File:** the file to download
- **Preview image:** optional

### Logo Slider — `bridge/logo-slider`
A moving row of client or partner logos, with an optional second row.
- **Logos:**
  - Width: Full width / Wide [Full width]
  - Image fit: Contain / Cover [Contain]
  - Logo size
  - Space between logos
  - Speed
  - Add a second row [off]
- **Label** (optional, typed above the logos): size (Small / Medium / Large / Extra large) [Medium] and alignment (left / centre / right) [left] are set from the block toolbar.
- **Add logos.** Each logo can be moved earlier or later, or removed. If the row fits the screen, it sits still instead of scrolling.

---

## Calls to action and forms

### Call to Action — `bridge/call-to-action`
A band with a heading, a line of copy and up to two buttons, over a photograph. For a flat colour band, use the Call to action patterns instead.
- **Layout:** Width: Wide / Narrow [Narrow]
- **Background:**
  - Background image
  - Darken image [50]
- **Graphic:** optional image with alt text
- **Mask shape** (shared panel)

### Contact Form — `bridge/contact-form`
Name, phone, email and message fields. Each submission is saved under **Enquiries**, the sender gets a thank-you email and the site is notified.
- **Layout:**
  - Width: Narrow / Wide [Narrow]
  - Draw the form on a card [on]
- **Form:**
  - Require a phone number [on]
  - Button label
  - Message after sending
- **Where enquiries go:** information panel

### FAQs — `bridge/faqs`
Questions that open to reveal their answers, taken from the FAQs entered in the admin.
- **Questions:**
  - Category [All categories]
  - Order: The order set on each one / A to Z / Newest first [Order set on each]
  - How many [0 = all]
- **Layout:**
  - Width: Narrow / Wide [Narrow]
  - One answer at a time [off]
- **Mask shape** (shared panel)

### Map — `bridge/map`
- **Map settings:**
  - Find a place / Address
  - Caption
  - Zoom [14]
  - Width: Wide / Narrow / Full window [Wide]
  - Height (px) [420]

---

## Site parts (no block settings)

These blocks take everything from **Theme Options** or **Site Options**. They have no sidebar settings.

| Block | Name | Source |
|---|---|---|
| Site Header | `bridge/header` | Theme Options: layout, menus, logo, top bar, button. Has a hidden `background` attribute (solid / transparent) that templates set. |
| Site Footer | `bridge/footer` | Theme Options and Site Options. Menus come from footer slots Explore / Services / Legal. The column headings are fixed in `src/blocks/footer/render.php`. |
| Breadcrumbs | `bridge/breadcrumb` | The trail from the front page, plus BreadcrumbList structured data. |
| Post Meta | `bridge/post-meta` | Date / categories / author. Which of these show is set site-wide in Theme Options. |
