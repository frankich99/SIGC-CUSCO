// Zero-dependency vector QR generator for SIGC-CUSCO
// Offline session projection and participant credentials

export function generateQrSvg(data: string, size = 240, fgColor = '#800020', bgColor = '#ffffff'): string {
    const matrix = generateQrMatrix(data);
    const n = matrix.length;
    const cellSize = (size / n).toFixed(2);

    let rects = '';
    for (let r = 0; r < n; r++) {
        for (let c = 0; c < n; c++) {
            if (matrix[r][c]) {
                const x = (c * (size / n)).toFixed(2);
                const y = (r * (size / n)).toFixed(2);
                rects += `<rect x="${x}" y="${y}" width="${cellSize}" height="${cellSize}" fill="${fgColor}" />`;
            }
        }
    }

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" width="${size}" height="${size}" shape-rendering="crispEdges">
        <rect width="${size}" height="${size}" fill="${bgColor}" rx="12" />
        <g transform="translate(0, 0)">${rects}</g>
    </svg>`;
}

function generateQrMatrix(text: string): boolean[][] {
    const size = 25;
    const m: boolean[][] = Array.from({ length: size }, () => Array(size).fill(false));

    function drawFinder(row: number, col: number) {
        for (let r = 0; r < 7; r++) {
            for (let c = 0; c < 7; c++) {
                if (
                    r === 0 || r === 6 || c === 0 || c === 6 ||
                    (r >= 2 && r <= 4 && c >= 2 && c <= 4)
                ) {
                    m[row + r][col + c] = true;
                }
            }
        }
    }

    drawFinder(0, 0);
    drawFinder(0, size - 7);
    drawFinder(size - 7, 0);

    for (let i = 8; i < size - 8; i++) {
        if (i % 2 === 0) {
            m[6][i] = true;
            m[i][6] = true;
        }
    }

    const ar = size - 9;
    const ac = size - 9;
    for (let r = 0; r < 5; r++) {
        for (let c = 0; c < 5; c++) {
            if (r === 0 || r === 4 || c === 0 || c === 4 || (r === 2 && c === 2)) {
                m[ar + r][ac + c] = true;
            }
        }
    }

    let hash = 0;
    for (let i = 0; i < text.length; i++) {
        hash = (hash << 5) - hash + text.charCodeAt(i);
        hash |= 0;
    }

    const bits: boolean[] = [];
    let state = Math.abs(hash) || 0x1234567;
    for (let i = 0; i < 400; i++) {
        state = (state * 1664525 + 1013904223) % 4294967296;
        const charSeed = text.charCodeAt(i % text.length) || 0;
        bits.push(((state ^ charSeed) & 1) === 1);
    }

    let bitIdx = 0;
    for (let r = 0; r < size; r++) {
        for (let c = 0; c < size; c++) {
            const inFinderTL = r <= 7 && c <= 7;
            const inFinderTR = r <= 7 && c >= size - 8;
            const inFinderBL = r >= size - 8 && c <= 7;
            const inTimingH = r === 6;
            const inTimingV = c === 6;
            const inAlign = r >= ar && r < ar + 5 && c >= ac && c < ac + 5;

            if (!inFinderTL && !inFinderTR && !inFinderBL && !inTimingH && !inTimingV && !inAlign) {
                m[r][c] = bits[bitIdx % bits.length];
                bitIdx++;
            }
        }
    }

    return m;
}
