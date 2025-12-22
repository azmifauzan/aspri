
import { useTranslation } from 'react-i18next';
import ReactMarkdown from 'react-markdown';
import Navbar from '../components/Navbar';
import Footer from '../components/Footer';

export default function PrivacyPolicyPage() {
  const { t } = useTranslation();

  return (
    <>
      <Navbar />
      <main className="bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200">
        <div className="max-w-4xl mx-auto px-4 py-16">
          <h1 className="text-4xl font-bold text-center mb-8 text-brand">
            {t('legal.privacy_policy.title')}
          </h1>
          <div className="prose dark:prose-invert max-w-none text-justify">
            <ReactMarkdown>{t('legal.privacy_policy.content')}</ReactMarkdown>
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}