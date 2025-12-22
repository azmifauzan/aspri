// src/services/contactService.ts
import api from './api';

export interface Contact {
  id: string;
  name: string;
  email?: string;
  phone?: string;
  etag?: string;
}

export const getContacts = async (): Promise<Contact[]> => {
  const response = await api.get('/contacts/');
  return response.data;
};

export const createContact = async (contactData: Omit<Contact, 'id' | 'etag'>): Promise<any> => {
  const response = await api.post('/contacts/', contactData);
  return response.data;
};

export const updateContact = async (resourceName: string, contactData: Partial<Contact>): Promise<any> => {
  const response = await api.put(`/contacts/${resourceName}`, contactData);
  return response.data;
};

export const deleteContact = async (resourceName: string): Promise<void> => {
  await api.delete(`/contacts/${resourceName}`);
};
