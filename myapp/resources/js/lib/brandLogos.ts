import {
    siApple,
    siApplemusic,
    siClaude,
    siDiscord,
    siDropbox,
    siDuolingo,
    siFacebook,
    siFigma,
    siGithub,
    siGoogle,
    siGrab,
    siHbomax,
    siIcloud,
    siInstagram,
    siNetflix,
    siNotion,
    siPlaystation,
    siShopee,
    siSpotify,
    siSteam,
    siTiktok,
    siTwitch,
    siYamahamotorcorporation,
    siYoutube,
    siZoom,
} from 'simple-icons';

type Icon = { path: string; hex: string };

// ponytail: keyword -> icon, first match wins; simple-icons dropped Disney+, Microsoft, Adobe, Prime, OpenAI. Add a row to support a brand
const icons: [string, Icon][] = [
    ['nmax', siYamahamotorcorporation],
    ['yamaha', siYamahamotorcorporation],
    ['youtube', siYoutube],
    ['icloud', siIcloud],
    ['apple music', siApplemusic],
    ['apple', siApple],
    ['google', siGoogle],
    ['claude', siClaude],
    ['netflix', siNetflix],
    ['spotify', siSpotify],
    ['hbo', siHbomax],
    ['notion', siNotion],
    ['dropbox', siDropbox],
    ['github', siGithub],
    ['discord', siDiscord],
    ['tiktok', siTiktok],
    ['facebook', siFacebook],
    ['instagram', siInstagram],
    ['zoom', siZoom],
    ['figma', siFigma],
    ['duolingo', siDuolingo],
    ['twitch', siTwitch],
    ['steam', siSteam],
    ['shopee', siShopee],
    ['grab', siGrab],
    ['playstation', siPlaystation],
];

// logos simple-icons lacks, served from public/brands (Converge: Wikimedia Commons, public domain)
// Only the freely licensed bank marks (public domain on Commons) are bundled; other banks show a coloured letter tile
const images: [string, string][] = [
    ['converge', '/brands/converge.svg'],
    ['gcash', '/brands/banks/gcash.svg'],
    ['bdo', '/brands/banks/bdo.svg'],
    ['landbank', '/brands/banks/landbank.svg'],
    ['land bank', '/brands/banks/landbank.svg'],
    ['pnb', '/brands/banks/pnb.svg'],
    ['unionbank', '/brands/banks/unionbank.svg'],
    ['union bank', '/brands/banks/unionbank.svg'],
    ['metrobank', '/brands/banks/metrobank.gif'],
];

const home = /\b(rent|house|home|mortgage)\b/i;

/** The built-in logo for a name, or null when the user should upload one. */
export function findBrand(
    name: string,
): { icon?: Icon; image?: string; home?: true } | null {
    const n = name.toLowerCase();
    const image = images.find(([k]) => n.includes(k))?.[1];
    const icon = icons.find(([k]) => n.includes(k))?.[1];

    if (image) {
        return { image };
    }

    if (home.test(name)) {
        return { home: true };
    }

    return icon ? { icon } : null;
}
