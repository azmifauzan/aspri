import Hero from "@/components/Hero";
import ProblemSolution from "@/components/ProblemSolution";
import FeatureSection from "@/components/FeatureSection";
import ChatPreview from "@/components/ChatPreview";
import Footer from "@/components/Footer";
import Navbar from "@/components/Navbar";

export default function LandingPage() {
  return (
    <>
      <Navbar />
      <Hero />
      <ProblemSolution />
      <FeatureSection />
      <ChatPreview />
      <Footer /> 
    </>
  );
}
