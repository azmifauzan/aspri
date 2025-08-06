import React from 'react';

const SummaryTab: React.FC = () => {
  return (
    <div>
      <h2 className="text-xl font-bold mb-4">Ringkasan</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="card bg-base-100 shadow-xl">
          <div className="card-body">
            <h3 className="card-title">Pemasukan vs Pengeluaran</h3>
            <p>Chart will go here.</p>
          </div>
        </div>
        <div className="card bg-base-100 shadow-xl">
          <div className="card-body">
            <h3 className="card-title">Pengeluaran per Kategori</h3>
            <p>Chart will go here.</p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SummaryTab;
