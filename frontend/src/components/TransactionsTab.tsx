import React from 'react';

const TransactionsTab: React.FC = () => {
  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-xl font-bold">Mutasi</h2>
        <button className="btn btn-primary">Tambah Transaksi</button>
      </div>
      <div className="overflow-x-auto">
        <table className="table w-full">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Deskripsi</th>
              <th>Kategori</th>
              <th>Tipe</th>
              <th>Jumlah</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {/* Placeholder row */}
            <tr>
              <td>2025-08-05</td>
              <td>Gaji</td>
              <td>Pemasukan</td>
              <td>Pemasukan</td>
              <td>Rp 10.000.000</td>
              <td>
                <button className="btn btn-sm btn-outline">Edit</button>
                <button className="btn btn-sm btn-outline btn-error ml-2">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default TransactionsTab;
