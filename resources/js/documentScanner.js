/**
 * NativePHP Mobile Document Scanner — JavaScript bridge
 *
 * Usage in Vue / React / vanilla JS:
 *
 *   import { scan, scanToPdf, scanToJpegs, scanSinglePage } from './documentScanner'
 *
 *   const result = await scan({ maxPages: 5, outputPdf: true })
 *   if (result && !result.cancelled) {
 *     console.log(result.pdf)      // '/private/var/.../document.pdf'
 *     console.log(result.pages)    // ['/private/var/.../page_1.jpg', ...]
 *     console.log(result.pageCount)
 *   }
 */

const BASE_URL = '/_native/api/call'

async function bridgeCall(method, params = {}) {
    const response = await fetch(BASE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ method, params }),
    })
    return response.json()
}

/**
 * Open the native document scanner.
 *
 * @param {object} options
 * @param {number}  [options.maxPages=0]             0 = unlimited
 * @param {boolean} [options.allowGalleryImport=false] Android only
 * @param {string}  [options.mode='full']            'base' | 'filter' | 'full'
 * @param {boolean} [options.outputPdf=true]
 * @param {boolean} [options.outputJpegs=true]
 *
 * @returns {Promise<{cancelled: boolean, pageCount: number, pages: string[], pdf: string|null}|null>}
 */
export async function scan(options = {}) {
    const params = {
        maxPages: options.maxPages ?? 0,
        allowGalleryImport: options.allowGalleryImport ?? false,
        mode: options.mode ?? 'full',
        outputPdf: options.outputPdf ?? true,
        outputJpegs: options.outputJpegs ?? true,
    }

    const response = await bridgeCall('DocumentScanner.Scan', params)
    return response?.data ?? null
}

/**
 * Scan and return only the PDF path.
 *
 * @param {number} [maxPages=0]
 * @returns {Promise<string|null>}
 */
export async function scanToPdf(maxPages = 0) {
    const result = await scan({ maxPages, outputPdf: true, outputJpegs: false })
    return result?.pdf ?? null
}

/**
 * Scan and return only the JPEG page paths.
 *
 * @param {number} [maxPages=0]
 * @returns {Promise<string[]|null>}
 */
export async function scanToJpegs(maxPages = 0) {
    const result = await scan({ maxPages, outputPdf: false, outputJpegs: true })
    return result?.pages ?? null
}

/**
 * Scan a single page and return its JPEG path.
 *
 * @returns {Promise<string|null>}
 */
export async function scanSinglePage() {
    const result = await scan({ maxPages: 1, outputPdf: false, outputJpegs: true })
    return result?.pages?.[0] ?? null
}
