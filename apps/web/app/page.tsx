import Nav from "@/components/Nav";
import HeroSection from "@/components/HeroSection";
import ChessBoard from "@/components/ChessBoard";
import FeaturesSection from "@/components/FeaturesSection";
import TrendSection from "@/components/TrendSection";
import CtaSection from "@/components/CtaSection";
import Footer from "@/components/Footer";

export default function HomePage() {
  return (
    <div style={{ background: "var(--bg)", color: "var(--text)", minHeight: "100vh" }}>
      <Nav />

      <main>
        <div className="animate-in">
          <HeroSection />
        </div>

        <div className="animate-in delay-2">
          <ChessBoard />
        </div>

        <div className="animate-in delay-3">
          <FeaturesSection />
        </div>

        <div className="animate-in delay-4">
          <TrendSection />
        </div>

        <CtaSection />
      </main>

      <Footer />
    </div>
  );
}
