// ============================================================================
// Purple Festival Gujarat 2027 — Original Editorial Crowd Character Registry
// Maps all 60 unique illustrated crowd characters across 4 sprite sheets
// plus the 3 stationary central welcoming hosts in signature Purple Festival attire.
// ============================================================================

export type CharacterCategory =
  | "wheelchair-users"
  | "white-cane-users"
  | "prosthetic-users"
  | "hearing-aid-users"
  | "mobility-aid-users"
  | "sign-language-users"
  | "students"
  | "elderly"
  | "families"
  | "volunteers"
  | "welcoming-group";

export interface CrowdCharacterMetadata {
  id: string;
  cellIndex: number; // 0..59 across the four 5x3 sheets
  sheetIndex: 0 | 1 | 2 | 3;
  col: number; // 0..4
  row: number; // 0..2
  category: CharacterCategory;
  description: string;
  scale: number;
}

export const WELCOMING_GROUP_CHARACTERS = [
  {
    id: "welcome-gujarati-namaste",
    category: "welcoming-group" as const,
    description:
      "Young Indian woman in a royal-purple and lavender Gujarati chaniya choli and embroidered dupatta offering a respectful Namaste gesture.",
  },
  {
    id: "welcome-wheelchair-host",
    category: "welcoming-group" as const,
    description:
      "Confident Indian man in a royal-purple overshirt seated naturally in a purple-accented modern manual wheelchair, smiling and waving welcome.",
  },
  {
    id: "welcome-prosthetic-host",
    category: "welcoming-group" as const,
    description:
      "Smiling young Indian man in a deep royal-purple Nehru jacket with a visible carbon-fiber lower-limb prosthetic, gesturing welcome.",
  },
] as const;

export const CROWD_CHARACTER_REGISTRY: CrowdCharacterMetadata[] = [
  // Sheet 0 (0..14 — People with visible disabilities & assistive devices)
  { id: "char-01", cellIndex: 0, sheetIndex: 0, col: 0, row: 0, category: "wheelchair-users", description: "Young woman in manual wheelchair smiling confidently", scale: 1.0 },
  { id: "char-02", cellIndex: 1, sheetIndex: 0, col: 1, row: 0, category: "white-cane-users", description: "Bearded man with sunglasses navigating with a long white cane", scale: 1.01 },
  { id: "char-03", cellIndex: 2, sheetIndex: 0, col: 2, row: 0, category: "prosthetic-users", description: "Young woman with bionic prosthetic forearm holding a notebook", scale: 0.96 },
  { id: "char-04", cellIndex: 3, sheetIndex: 0, col: 3, row: 0, category: "wheelchair-users", description: "Older gentleman in kurta and glasses seated in a manual wheelchair", scale: 1.0 },
  { id: "char-05", cellIndex: 4, sheetIndex: 0, col: 4, row: 0, category: "mobility-aid-users", description: "Young man walking with a forearm crutch and backpack", scale: 1.01 },
  { id: "char-06", cellIndex: 5, sheetIndex: 0, col: 0, row: 1, category: "white-cane-users", description: "Woman in traditional saree holding a long white navigation cane", scale: 0.99 },
  { id: "char-07", cellIndex: 6, sheetIndex: 0, col: 1, row: 1, category: "hearing-aid-users", description: "Smiling teenager with a visible behind-the-ear cochlear implant / hearing aid", scale: 0.93 },
  { id: "char-08", cellIndex: 7, sheetIndex: 0, col: 2, row: 1, category: "sign-language-users", description: "Two friends communicating warmly using Indian Sign Language-inspired gestures", scale: 0.98 },
  { id: "char-09", cellIndex: 8, sheetIndex: 0, col: 3, row: 1, category: "prosthetic-users", description: "Student in hoodie with prosthetic forearm and festival lanyard", scale: 0.96 },
  { id: "char-10", cellIndex: 9, sheetIndex: 0, col: 4, row: 1, category: "elderly", description: "Elderly Gujarati grandfather with cap and curved wooden walking stick", scale: 0.96 },
  { id: "char-11", cellIndex: 10, sheetIndex: 0, col: 0, row: 2, category: "wheelchair-users", description: "College student in modern wheelchair wearing headphones and festival pass", scale: 1.0 },
  { id: "char-12", cellIndex: 11, sheetIndex: 0, col: 1, row: 2, category: "hearing-aid-users", description: "Middle-aged teacher with glasses and behind-the-ear hearing aid holding a book", scale: 0.95 },
  { id: "char-13", cellIndex: 12, sheetIndex: 0, col: 2, row: 2, category: "mobility-aid-users", description: "Older woman in salwar kameez using a two-handled walker frame", scale: 0.97 },
  { id: "char-14", cellIndex: 13, sheetIndex: 0, col: 3, row: 2, category: "volunteers", description: "Young man with dark glasses carrying a sling bag", scale: 0.96 },
  { id: "char-15", cellIndex: 14, sheetIndex: 0, col: 4, row: 2, category: "mobility-aid-users", description: "Cheerful young woman using dual forearm crutches in contemporary kurta", scale: 0.98 },

  // Sheet 1 (15..29 — Students, elderly, families, volunteers, cultural participants)
  { id: "char-16", cellIndex: 15, sheetIndex: 1, col: 0, row: 0, category: "students", description: "College student with backpack and skateboard", scale: 0.96 },
  { id: "char-17", cellIndex: 16, sheetIndex: 1, col: 1, row: 0, category: "families", description: "Young Gujarati woman in patterned saree and bindi", scale: 0.94 },
  { id: "char-18", cellIndex: 17, sheetIndex: 1, col: 2, row: 0, category: "volunteers", description: "Laughing bearded man in open casual shirt", scale: 0.98 },
  { id: "char-19", cellIndex: 18, sheetIndex: 1, col: 3, row: 0, category: "students", description: "Young woman with ponytail checking her smartphone", scale: 0.95 },
  { id: "char-20", cellIndex: 19, sheetIndex: 1, col: 4, row: 0, category: "families", description: "Smiling Sikh man in neat turban and collared shirt", scale: 0.98 },
  { id: "char-21", cellIndex: 20, sheetIndex: 1, col: 0, row: 1, category: "elderly", description: "Grandmother with round glasses and traditional shawl", scale: 0.93 },
  { id: "char-22", cellIndex: 21, sheetIndex: 1, col: 1, row: 1, category: "students", description: "Excited child in hoodie holding a small toy robot", scale: 0.89 },
  { id: "char-23", cellIndex: 22, sheetIndex: 1, col: 2, row: 1, category: "volunteers", description: "Festival volunteer wearing lanyard badge and waving", scale: 0.96 },
  { id: "char-24", cellIndex: 23, sheetIndex: 1, col: 3, row: 1, category: "students", description: "Young woman wearing hijab and denim jacket", scale: 0.95 },
  { id: "char-25", cellIndex: 24, sheetIndex: 1, col: 4, row: 1, category: "families", description: "Middle-aged man with moustache and pocket pen", scale: 0.96 },
  { id: "char-26", cellIndex: 25, sheetIndex: 1, col: 0, row: 2, category: "students", description: "Young woman with wavy hair and round glasses", scale: 0.94 },
  { id: "char-27", cellIndex: 26, sheetIndex: 1, col: 1, row: 2, category: "families", description: "Father holding a happy toddler on his shoulder", scale: 1.02 },
  { id: "char-28", cellIndex: 27, sheetIndex: 1, col: 2, row: 2, category: "volunteers", description: "Young folk artist in embroidered Gujarati vest", scale: 0.95 },
  { id: "char-29", cellIndex: 28, sheetIndex: 1, col: 3, row: 2, category: "students", description: "Schoolgirl with braided pigtails holding a sketchbook", scale: 0.92 },
  { id: "char-30", cellIndex: 29, sheetIndex: 1, col: 4, row: 2, category: "elderly", description: "Senior gentleman with white beard and waistcoat", scale: 0.96 },

  // Sheet 2 (30..44 — Additional unique Indian & Gujarati festival participants)
  { id: "char-31", cellIndex: 30, sheetIndex: 2, col: 0, row: 0, category: "volunteers", description: "Young woman with bob haircut and cat-eye glasses", scale: 0.94 },
  { id: "char-32", cellIndex: 31, sheetIndex: 2, col: 1, row: 0, category: "families", description: "Cheerful Gujarati man in traditional embroidered kediyu kurta", scale: 0.97 },
  { id: "char-33", cellIndex: 32, sheetIndex: 2, col: 2, row: 0, category: "students", description: "College student with over-ear headphones and striped hoodie", scale: 0.96 },
  { id: "char-34", cellIndex: 33, sheetIndex: 2, col: 3, row: 0, category: "elderly", description: "Older woman with silver braided hair in floral-bordered saree", scale: 0.94 },
  { id: "char-35", cellIndex: 34, sheetIndex: 2, col: 4, row: 0, category: "white-cane-users", description: "Young woman with round sunglasses and denim vest using a white cane", scale: 0.96 },
  { id: "char-36", cellIndex: 35, sheetIndex: 2, col: 0, row: 1, category: "volunteers", description: "Young photographer with man-bun holding a DSLR camera", scale: 0.97 },
  { id: "char-37", cellIndex: 36, sheetIndex: 2, col: 1, row: 1, category: "students", description: "Cheerful young girl with twin puffs hairstyle waving", scale: 0.89 },
  { id: "char-38", cellIndex: 37, sheetIndex: 2, col: 2, row: 1, category: "wheelchair-users", description: "Para-athlete in wheelchair wearing sports jersey and headband", scale: 0.99 },
  { id: "char-39", cellIndex: 38, sheetIndex: 2, col: 3, row: 1, category: "volunteers", description: "Healthcare professional with stethoscope and ID badge", scale: 0.95 },
  { id: "char-40", cellIndex: 39, sheetIndex: 2, col: 4, row: 1, category: "prosthetic-users", description: "Young man with prosthetic forearm holding a rolled festival poster", scale: 0.97 },
  { id: "char-41", cellIndex: 40, sheetIndex: 2, col: 0, row: 2, category: "elderly", description: "Elderly gentleman with round spectacles and draped shawl", scale: 0.94 },
  { id: "char-42", cellIndex: 41, sheetIndex: 2, col: 1, row: 2, category: "families", description: "Young woman in traditional bandhani head-dupatta and jhumkas", scale: 0.95 },
  { id: "char-43", cellIndex: 42, sheetIndex: 2, col: 2, row: 2, category: "hearing-aid-users", description: "Teenager with glasses and behind-the-ear hearing aid giving thumbs up", scale: 0.92 },
  { id: "char-44", cellIndex: 43, sheetIndex: 2, col: 3, row: 2, category: "volunteers", description: "Visual artist with beret and paintbrushes in apron pocket", scale: 0.95 },
  { id: "char-45", cellIndex: 44, sheetIndex: 2, col: 4, row: 2, category: "students", description: "Young musician with curly hair and guitar strap over shoulder", scale: 0.97 },

  // Sheet 3 (45..59 — Additional unique delegates, families, elders & youth)
  { id: "char-46", cellIndex: 45, sheetIndex: 3, col: 0, row: 0, category: "volunteers", description: "Young delegate in blazer with festival credential badge", scale: 0.97 },
  { id: "char-47", cellIndex: 46, sheetIndex: 3, col: 1, row: 0, category: "students", description: "Woman with long curly hair and rectangular glasses holding a book", scale: 0.96 },
  { id: "char-48", cellIndex: 47, sheetIndex: 3, col: 2, row: 0, category: "elderly", description: "Grandmother with nose ring and patterned pallu over head", scale: 0.94 },
  { id: "char-49", cellIndex: 48, sheetIndex: 3, col: 3, row: 0, category: "students", description: "Young boy in cap holding a cricket bat over his shoulder", scale: 0.92 },
  { id: "char-50", cellIndex: 49, sheetIndex: 3, col: 4, row: 0, category: "wheelchair-users", description: "Young woman in wheelchair wearing embroidered kurti and necklace", scale: 0.98 },
  { id: "char-51", cellIndex: 50, sheetIndex: 3, col: 0, row: 1, category: "students", description: "Bearded young man in beanie cap and hoodie", scale: 0.96 },
  { id: "char-52", cellIndex: 51, sheetIndex: 3, col: 1, row: 1, category: "families", description: "Middle-aged woman with wavy hair and pearl earrings gesturing in conversation", scale: 0.94 },
  { id: "char-53", cellIndex: 52, sheetIndex: 3, col: 2, row: 1, category: "families", description: "Man with handlebar moustache and traditional waistcoat", scale: 0.97 },
  { id: "char-54", cellIndex: 53, sheetIndex: 3, col: 3, row: 1, category: "hearing-aid-users", description: "Young woman with visible cochlear implant holding a coffee cup", scale: 0.95 },
  { id: "char-55", cellIndex: 54, sheetIndex: 3, col: 4, row: 1, category: "mobility-aid-users", description: "College student using a forearm crutch in graphic t-shirt", scale: 0.95 },
  { id: "char-56", cellIndex: 55, sheetIndex: 3, col: 0, row: 2, category: "families", description: "Young woman in salwar suit and scarf carrying a tote bag", scale: 0.95 },
  { id: "char-57", cellIndex: 56, sheetIndex: 3, col: 1, row: 2, category: "elderly", description: "Older gentleman with salt-and-pepper beard and square glasses", scale: 0.97 },
  { id: "char-58", cellIndex: 57, sheetIndex: 3, col: 2, row: 2, category: "sign-language-users", description: "Young man signing with an expressive two-handed gesture", scale: 0.95 },
  { id: "char-59", cellIndex: 58, sheetIndex: 3, col: 3, row: 2, category: "students", description: "Little girl with headband and bow holding a pinwheel", scale: 0.88 },
  { id: "char-60", cellIndex: 59, sheetIndex: 3, col: 4, row: 2, category: "volunteers", description: "Young woman with high bun and hoop earrings in structured blazer", scale: 0.96 },
];
