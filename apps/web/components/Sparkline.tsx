"use client";

import { useRef, useEffect } from "react";

interface SparklineProps {
  data: readonly number[];
  minValue?: number;
  maxValue?: number;
}

export default function Sparkline({ data, minValue = 50, maxValue = 90 }: SparklineProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const container = containerRef.current;
    const canvas = canvasRef.current;
    if (!container || !canvas) return;

    function draw() {
      if (!container || !canvas) return;
      const dpr = window.devicePixelRatio || 1;
      const cssW = container.clientWidth;
      const cssH = 60;

      canvas.style.width = `${cssW}px`;
      canvas.style.height = `${cssH}px`;
      canvas.width = cssW * dpr;
      canvas.height = cssH * dpr;

      const ctx = canvas.getContext("2d");
      if (!ctx) return;
      ctx.scale(dpr, dpr);

      const pad = 12;
      const W = cssW;
      const H = cssH;
      const xs = data.map((_, i) => pad + (i / (data.length - 1)) * (W - pad * 2));
      const ys = data.map(
        (v) => H - pad - ((v - minValue) / (maxValue - minValue)) * (H - pad * 2)
      );

      const grad = ctx.createLinearGradient(0, 0, 0, H);
      grad.addColorStop(0, "rgba(201,168,76,0.15)");
      grad.addColorStop(1, "rgba(201,168,76,0)");

      ctx.beginPath();
      ctx.moveTo(xs[0], ys[0]);
      for (let i = 1; i < xs.length; i++) {
        const mx = (xs[i - 1] + xs[i]) / 2;
        ctx.bezierCurveTo(mx, ys[i - 1], mx, ys[i], xs[i], ys[i]);
      }
      ctx.lineTo(xs[xs.length - 1], H);
      ctx.lineTo(xs[0], H);
      ctx.closePath();
      ctx.fillStyle = grad;
      ctx.fill();

      ctx.beginPath();
      ctx.moveTo(xs[0], ys[0]);
      for (let i = 1; i < xs.length; i++) {
        const mx = (xs[i - 1] + xs[i]) / 2;
        ctx.bezierCurveTo(mx, ys[i - 1], mx, ys[i], xs[i], ys[i]);
      }
      ctx.strokeStyle = "rgba(201,168,76,0.6)";
      ctx.lineWidth = 1.5;
      ctx.stroke();

      data.forEach((_, i) => {
        ctx.beginPath();
        ctx.arc(xs[i], ys[i], 3, 0, Math.PI * 2);
        ctx.fillStyle = "#c9a84c";
        ctx.fill();
      });
    }

    draw();

    const ro = new ResizeObserver(draw);
    ro.observe(container);
    return () => ro.disconnect();
  }, [data, minValue, maxValue]);

  return (
    <div ref={containerRef} className="w-full" style={{ height: 60 }}>
      <canvas ref={canvasRef} aria-label="Accuracy sparkline chart" role="img" />
    </div>
  );
}
