import React, { useState } from 'react';
import SummaryTab from '../components/SummaryTab';
import TransactionsTab from '../components/TransactionsTab';

const FinancePage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'summary' | 'transactions'>('summary');

  return (
    <div className="container mx-auto p-4">
      <h1 className="text-2xl font-bold mb-4">Keuangan</h1>
      <div className="tabs">
        <button
          className={`tab tab-bordered ${activeTab === 'summary' ? 'tab-active' : ''}`}
          onClick={() => setActiveTab('summary')}
        >
          Ringkasan
        </button>
        <button
          className={`tab tab-bordered ${activeTab === 'transactions' ? 'tab-active' : ''}`}
          onClick={() => setActiveTab('transactions')}
        >
          Mutasi
        </button>
      </div>
      <div className="mt-4">
        {activeTab === 'summary' && <SummaryTab />}
        {activeTab === 'transactions' && <TransactionsTab />}
      </div>
    </div>
  );
};

export default FinancePage;
