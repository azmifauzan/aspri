import { useTranslation } from 'react-i18next';
import Navbar from '../components/Navbar';
import Footer from '../components/Footer';

export default function TermsOfServicePage() {
  const { t } = useTranslation();

  return (
    <>
      <Navbar />
      <main className="bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200">
        <div className="max-w-4xl mx-auto px-4 py-16">
          <h1 className="text-4xl font-bold text-center mb-8 text-brand">
            {t('legal.terms_of_service.title')}
          </h1>
          <div className="prose dark:prose-invert max-w-none text-justify">
            <p>{t('legal.terms_of_service.content')}</p>
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}