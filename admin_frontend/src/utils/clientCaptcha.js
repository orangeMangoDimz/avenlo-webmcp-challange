/**
 * 浏览器端 Canvas 图片验证码（与后端无关）。
 */

const CHARSET = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ";

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function randomRgb(alpha = 1) {
  return `rgba(${randomInt(40, 120)},${randomInt(40, 120)},${randomInt(50, 140)},${alpha})`;
}

function randomDarkRgb() {
  return `rgb(${randomInt(20, 90)},${randomInt(30, 100)},${randomInt(40, 120)})`;
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {object} [opts]
 * @param {number} [opts.width=120]
 * @param {number} [opts.height=40]
 * @param {number} [opts.length=4]
 * @returns {{ answer: string }}
 */
export function drawCanvasCaptcha(canvas, opts = {}) {
  const width = opts.width != null ? opts.width : 120;
  const height = opts.height != null ? opts.height : 40;
  const length = opts.length != null ? opts.length : 4;

  const dpr =
    typeof window !== "undefined"
      ? Math.min(window.devicePixelRatio || 1, 2)
      : 1;
  canvas.width = Math.floor(width * dpr);
  canvas.height = Math.floor(height * dpr);
  canvas.style.width = `${width}px`;
  canvas.style.height = `${height}px`;

  const ctx = canvas.getContext("2d");
  ctx.setTransform(1, 0, 0, 1, 0, 0);
  ctx.scale(dpr, dpr);

  ctx.fillStyle = "#f4f6f9";
  ctx.fillRect(0, 0, width, height);

  for (let i = 0; i < 5; i++) {
    ctx.strokeStyle = randomRgb(0.35);
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(Math.random() * width, Math.random() * height);
    ctx.bezierCurveTo(
      Math.random() * width,
      Math.random() * height,
      Math.random() * width,
      Math.random() * height,
      Math.random() * width,
      Math.random() * height,
    );
    ctx.stroke();
  }

  for (let i = 0; i < 50; i++) {
    ctx.fillStyle = randomRgb(0.45);
    ctx.fillRect(Math.random() * width, Math.random() * height, 1.2, 1.2);
  }

  let answer = "";
  for (let i = 0; i < length; i++) {
    answer += CHARSET[randomInt(0, CHARSET.length - 1)];
  }

  const fontSize = Math.min(24, Math.floor(height * 0.58));
  ctx.textBaseline = "middle";
  ctx.font = `bold ${fontSize}px Arial, "Helvetica Neue", "Segoe UI", sans-serif`;

  const step = width / (length + 0.6);
  for (let i = 0; i < length; i++) {
    const ch = answer[i];
    const x = step * (i + 0.45) + (Math.random() * 4 - 2);
    const y = height / 2 + (Math.random() * 5 - 2.5);
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(Math.random() * 0.55 - 0.275);
    ctx.fillStyle = randomDarkRgb();
    ctx.fillText(ch, 0, 0);
    ctx.restore();
  }

  return { answer };
}

export function compareCaptcha(input, answer) {
  const a = (input == null ? "" : String(input)).trim().toUpperCase();
  const b = (answer == null ? "" : String(answer)).trim().toUpperCase();
  return a.length > 0 && a === b;
}
