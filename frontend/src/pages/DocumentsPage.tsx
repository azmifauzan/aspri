// src/pages/DocumentsPage.tsx
import { useState, useEffect } from 'react';
import axios from 'axios';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import {
  Upload,
  Search,
  File,
  FileText,
  FileType,
  Image,
  Trash2,
  Edit,
  X,
  Check,
  Loader2
} from 'lucide-react';

// API base URL
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8888';

// Document types and icons
const documentIcons: Record<string, any> = {
  pdf: FileType,
  docx: FileText,
  doc: FileText,
  txt: FileText,
  jpg: Image,
  jpeg: Image,
  png: Image,
  default: File
};

// Document interface
interface Document {
  id: number;
  user_id: number;
  filename: string;
  file_type: string;
  file_size: number;
  created_at: string;
  updated_at: string;
  minio_object_name: string;
}

// Search result interface
interface SearchResult {
  document_id: number;
  chunk_id: number;
  chunk_text: string;
  similarity_score: number;
  document_filename: string;
  document_file_type: string;
}

export default function DocumentsPage() {
  const { t } = useTranslation();
  const { token } = useAuth();
  const [documents, setDocuments] = useState<Document[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [uploadModalOpen, setUploadModalOpen] = useState(false);
  const [searchModalOpen, setSearchModalOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [isUploading, setIsUploading] = useState(false);
  const [editingDocumentId, setEditingDocumentId] = useState<number | null>(null);
  const [newFilename, setNewFilename] = useState('');
  const [error, setError] = useState('');

  // Fetch documents on component mount
  useEffect(() => {
    fetchDocuments();
  }, []);

  // Fetch documents from API
  const fetchDocuments = async () => {
    if (!token) return;
    
    setIsLoading(true);
    try {
      const response = await axios.get(`${API_BASE_URL}/documents`, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });
      setDocuments(response.data.documents);
    } catch (error: any) {
      console.error('Error fetching documents:', error);
      const errorMessage = error.response?.data?.detail || error.message || t('documents.error_fetching');
      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  // Handle file selection
  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    if (event.target.files && event.target.files.length > 0) {
      setSelectedFile(event.target.files[0]);
    }
  };

  // Upload document
  const uploadDocument = async () => {
    if (!selectedFile || !token) return;

    setIsUploading(true);
    setUploadProgress(0);
    setError('');
    
    try {
      // Create form data
      const formData = new FormData();
      formData.append('file', selectedFile);

      // Upload file with explicit authorization header
      await axios.post(`${API_BASE_URL}/documents/upload`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
          'Authorization': `Bearer ${token}`,
        },
        onUploadProgress: (progressEvent) => {
          if (progressEvent.total) {
            const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            setUploadProgress(progress);
          }
        }
      });

      // Reset and close modal
      setSelectedFile(null);
      setUploadModalOpen(false);
      setUploadProgress(0);
      
      // Refresh documents list
      fetchDocuments();
    } catch (error: any) {
      console.error('Error uploading document:', error);
      const errorMessage = error.response?.data?.detail || error.message || t('documents.error_uploading');
      setError(errorMessage);
    } finally {
      setIsUploading(false);
    }
  };

  // Delete document
  const deleteDocument = async (documentId: number) => {
    if (!confirm(t('documents.confirm_delete')) || !token) return;

    setIsLoading(true);
    try {
      await axios.delete(`${API_BASE_URL}/documents/${documentId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });
      fetchDocuments();
    } catch (error: any) {
      console.error('Error deleting document:', error);
      const errorMessage = error.response?.data?.detail || error.message || t('documents.error_deleting');
      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  // Start editing document name
  const startEditing = (document: Document) => {
    setEditingDocumentId(document.id);
    setNewFilename(document.filename);
  };

  // Save edited document name
  const saveDocumentName = async (documentId: number) => {
    if (!newFilename.trim() || !token) return;

    setIsLoading(true);
    try {
      await axios.put(`${API_BASE_URL}/documents/${documentId}`, {
        filename: newFilename
      }, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });
      setEditingDocumentId(null);
      fetchDocuments();
    } catch (error: any) {
      console.error('Error updating document:', error);
      const errorMessage = error.response?.data?.detail || error.message || t('documents.error_updating');
      setError(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  // Cancel editing
  const cancelEditing = () => {
    setEditingDocumentId(null);
    setNewFilename('');
  };

  // Search documents
  const searchDocuments = async () => {
    if (!searchQuery.trim() || !token) return;

    setIsSearching(true);
    try {
      const response = await axios.post(`${API_BASE_URL}/documents/search`, {
        query: searchQuery,
        limit: 10
      }, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });
      setSearchResults(response.data.results);
    } catch (error: any) {
      console.error('Error searching documents:', error);
      const errorMessage = error.response?.data?.detail || error.message || t('documents.error_searching');
      setError(errorMessage);
    } finally {
      setIsSearching(false);
    }
  };

  // Format file size
  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  // Get icon for document type
  const getDocumentIcon = (fileType: string) => {
    const IconComponent = documentIcons[fileType.toLowerCase()] || documentIcons.default;
    return <IconComponent size={20} />;
  };

  return (
    <div className="container mx-auto p-4">
      {/* Header */}
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-zinc-900 dark:text-white">
          {/* {t('documents.title')} */}
        </h1>
        <div className="flex space-x-2">
          <button
            onClick={() => setSearchModalOpen(true)}
            className="px-4 py-2 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-700 dark:hover:bg-zinc-600 rounded-lg flex items-center"
          >
            <Search size={18} className="mr-2" />
            {t('documents.search')}
          </button>
          <button
            onClick={() => setUploadModalOpen(true)}
            className="px-4 py-2 bg-brand hover:bg-brand/90 text-white rounded-lg flex items-center"
          >
            <Upload size={18} className="mr-2" />
            {t('documents.upload')}
          </button>
        </div>
      </div>

      {/* Error message */}
      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
          <span>{error}</span>
          <button onClick={() => setError('')}>
            <X size={18} />
          </button>
        </div>
      )}

      {/* Documents list */}
      <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm overflow-hidden">
        {isLoading ? (
          <div className="flex justify-center items-center p-8">
            <Loader2 size={24} className="animate-spin mr-2" />
            <span>{t('documents.loading')}</span>
          </div>
        ) : documents.length === 0 ? (
          <div className="text-center p-8">
            <File size={48} className="mx-auto mb-4 text-zinc-400" />
            <h3 className="text-lg font-medium text-zinc-900 dark:text-white mb-2">
              {t('documents.no_documents')}
            </h3>
            <p className="text-zinc-500 dark:text-zinc-400 mb-4">
              {t('documents.upload_first_document')}
            </p>
            <button
              onClick={() => setUploadModalOpen(true)}
              className="px-4 py-2 bg-brand hover:bg-brand/90 text-white rounded-lg inline-flex items-center"
            >
              <Upload size={18} className="mr-2" />
              {t('documents.upload')}
            </button>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-zinc-50 dark:bg-zinc-700">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">
                    {t('documents.filename')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">
                    {t('documents.type')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">
                    {t('documents.size')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">
                    {t('documents.uploaded')}
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-300 uppercase tracking-wider">
                    {t('documents.actions')}
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-200 dark:divide-zinc-700">
                {documents.map((document) => (
                  <tr key={document.id} className="hover:bg-zinc-50 dark:hover:bg-zinc-750">
                    <td className="px-6 py-4 whitespace-nowrap">
                      {editingDocumentId === document.id ? (
                        <div className="flex items-center">
                          <input
                            type="text"
                            value={newFilename}
                            onChange={(e) => setNewFilename(e.target.value)}
                            className="border border-zinc-300 dark:border-zinc-600 rounded px-2 py-1 mr-2 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white"
                            autoFocus
                          />
                          <button
                            onClick={() => saveDocumentName(document.id)}
                            className="text-green-500 hover:text-green-600 mr-1"
                          >
                            <Check size={18} />
                          </button>
                          <button
                            onClick={cancelEditing}
                            className="text-red-500 hover:text-red-600"
                          >
                            <X size={18} />
                          </button>
                        </div>
                      ) : (
                        <div className="flex items-center">
                          {getDocumentIcon(document.file_type)}
                          <span className="ml-2 text-zinc-900 dark:text-white">
                            {document.filename}
                          </span>
                        </div>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                      {document.file_type.toUpperCase()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                      {formatFileSize(document.file_size)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                      {new Date(document.created_at).toLocaleDateString()}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right">
                      <div className="flex justify-end space-x-2">
                        <button
                          onClick={() => startEditing(document)}
                          className="text-blue-500 hover:text-blue-600"
                          title={t('documents.edit')}
                        >
                          <Edit size={18} />
                        </button>
                        <button
                          onClick={() => deleteDocument(document.id)}
                          className="text-red-500 hover:text-red-600"
                          title={t('documents.delete')}
                        >
                          <Trash2 size={18} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Upload Modal */}
      {uploadModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-zinc-800 rounded-lg shadow-lg p-6 w-full max-w-md">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-zinc-900 dark:text-white">
                {t('documents.upload_document')}
              </h2>
              <button
                onClick={() => setUploadModalOpen(false)}
                className="text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
              >
                <X size={24} />
              </button>
            </div>
            
            <div className="mb-4">
              <label className="block text-zinc-700 dark:text-zinc-300 mb-2">
                {t('documents.select_file')}
              </label>
              <div className="relative border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg p-4 text-center hover:border-brand hover:bg-brand/5 transition-colors">
                {selectedFile ? (
                  <div className="flex items-center justify-center">
                    {getDocumentIcon(selectedFile.name.split('.').pop() || 'default')}
                    <span className="ml-2 text-zinc-900 dark:text-white">
                      {selectedFile.name} ({formatFileSize(selectedFile.size)})
                    </span>
                    <button
                      onClick={() => setSelectedFile(null)}
                      className="ml-2 text-red-500 hover:text-red-600"
                      title="Remove file"
                    >
                      <X size={16} />
                    </button>
                  </div>
                ) : (
                  <div>
                    <Upload size={32} className="mx-auto mb-2 text-zinc-400" />
                    <p className="text-zinc-500 dark:text-zinc-400">
                      {t('documents.drag_or_click')}
                    </p>
                  </div>
                )}
                <input
                  type="file"
                  onChange={handleFileSelect}
                  className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                  accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png"
                />
              </div>
            </div>
            
            {isUploading && (
              <div className="mb-4">
                <div className="h-2 bg-zinc-200 dark:bg-zinc-700 rounded-full">
                  <div
                    className="h-full bg-brand rounded-full"
                    style={{ width: `${uploadProgress}%` }}
                  ></div>
                </div>
                <p className="text-center mt-2 text-zinc-500 dark:text-zinc-400">
                  {uploadProgress}% {t('documents.uploaded')}
                </p>
              </div>
            )}
            
            <div className="flex justify-end space-x-2">
              <button
                onClick={() => setUploadModalOpen(false)}
                className="px-4 py-2 bg-zinc-200 hover:bg-zinc-300 dark:bg-zinc-700 dark:hover:bg-zinc-600 rounded-lg"
              >
                {t('documents.cancel')}
              </button>
              <button
                onClick={uploadDocument}
                disabled={!selectedFile || isUploading}
                className={`px-4 py-2 bg-brand text-white rounded-lg flex items-center ${
                  !selectedFile || isUploading ? 'opacity-50 cursor-not-allowed' : 'hover:bg-brand/90'
                }`}
              >
                {isUploading ? (
                  <>
                    <Loader2 size={18} className="animate-spin mr-2" />
                    {t('documents.uploading')}
                  </>
                ) : (
                  <>
                    <Upload size={18} className="mr-2" />
                    {t('documents.upload')}
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Search Modal */}
      {searchModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white dark:bg-zinc-800 rounded-lg shadow-lg p-6 w-full max-w-3xl">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-zinc-900 dark:text-white">
                {t('documents.search_documents')}
              </h2>
              <button
                onClick={() => setSearchModalOpen(false)}
                className="text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
              >
                <X size={24} />
              </button>
            </div>
            
            <div className="mb-4">
              <div className="flex">
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder={t('documents.search_placeholder')}
                  className="flex-1 border border-zinc-300 dark:border-zinc-600 rounded-l-lg px-4 py-2 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white"
                  onKeyDown={(e) => e.key === 'Enter' && searchDocuments()}
                />
                <button
                  onClick={searchDocuments}
                  disabled={isSearching || !searchQuery.trim()}
                  className={`px-4 py-2 bg-brand text-white rounded-r-lg flex items-center ${
                    isSearching || !searchQuery.trim() ? 'opacity-50 cursor-not-allowed' : 'hover:bg-brand/90'
                  }`}
                >
                  {isSearching ? (
                    <Loader2 size={18} className="animate-spin" />
                  ) : (
                    <Search size={18} />
                  )}
                </button>
              </div>
            </div>
            
            {/* Search results */}
            <div className="max-h-96 overflow-y-auto">
              {searchResults.length > 0 ? (
                <div className="space-y-4">
                  {searchResults.map((result) => (
                    <div
                      key={`${result.document_id}-${result.chunk_id}`}
                      className="bg-zinc-50 dark:bg-zinc-700 p-4 rounded-lg"
                    >
                      <div className="flex items-center mb-2">
                        {getDocumentIcon(result.document_file_type)}
                        <span className="ml-2 font-medium text-zinc-900 dark:text-white">
                          {result.document_filename}
                        </span>
                        <span className="ml-2 text-sm text-zinc-500 dark:text-zinc-400">
                          ({(result.similarity_score * 100).toFixed(1)}% {t('documents.match')})
                        </span>
                      </div>
                      <p className="text-zinc-700 dark:text-zinc-300 text-sm">
                        {result.chunk_text}
                      </p>
                    </div>
                  ))}
                </div>
              ) : isSearching ? (
                <div className="text-center p-8">
                  <Loader2 size={24} className="animate-spin mx-auto mb-2" />
                  <p className="text-zinc-500 dark:text-zinc-400">
                    {t('documents.searching')}
                  </p>
                </div>
              ) : searchQuery.trim() ? (
                <div className="text-center p-8">
                  <p className="text-zinc-500 dark:text-zinc-400">
                    {t('documents.no_results')}
                  </p>
                </div>
              ) : null}
            </div>
            
            <div className="flex justify-end mt-4">
              <button
                onClick={() => setSearchModalOpen(false)}
                className="px-4 py-2 bg-zinc-200 hover:bg-zinc-300 dark:bg-zinc-700 dark:hover:bg-zinc-600 rounded-lg"
              >
                {t('documents.close')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}