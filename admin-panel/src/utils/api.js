import axios from 'axios'

const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api/v1'

const api = axios.create({
  baseURL: API_BASE,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Request interceptor - add auth token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor - handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = `${import.meta.env.BASE_URL}login`
    }
    return Promise.reject(error)
  }
)

export default api

// Auth API
export const authApi = {
  login: (credentials) => api.post('/login', credentials),
  logout: () => api.post('/logout'),
  getUser: () => api.get('/user'),
  updateProfile: (data) => api.put('/profile', data),
  updatePassword: (data) => api.put('/password', data),
}

// Companies API
export const companiesApi = {
  getAll: (params) => api.get('/companies', { params }),
  get: (id) => api.get(`/companies/${id}`),
  create: (data) => api.post('/companies', data),
  update: (id, data) => api.put(`/companies/${id}`, data),
  delete: (id) => api.delete(`/companies/${id}`),
  stats: (id) => api.get(`/companies/${id}/stats`),
}

// Users API
export const usersApi = {
  getAll: (params) => api.get('/users', { params }),
  get: (id) => api.get(`/users/${id}`),
  create: (data) => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  delete: (id) => api.delete(`/users/${id}`),
  toggleStatus: (id) => api.put(`/users/${id}/toggle-status`),
  resetPassword: (id, password) => api.put(`/users/${id}/reset-password`, { password }),
  agentsList: () => api.get('/users/agents'),
}

// Clients API
export const clientsApi = {
  getAll: (params) => api.get('/clients', { params }),
  get: (id) => api.get(`/clients/${id}`),
  create: (data) => api.post('/clients', data),
  update: (id, data) => api.put(`/clients/${id}`, data),
  delete: (id) => api.delete(`/clients/${id}`),
  updateStatus: (id, status) => api.put(`/clients/${id}/status`, { status }),
  bulkAssign: (data) => api.post('/clients/bulk-assign', data),
}

// Tasks API
export const tasksApi = {
  getAll: (params) => api.get('/tasks', { params }),
  get: (id) => api.get(`/tasks/${id}`),
  create: (data) => api.post('/tasks', data),
  update: (id, data) => api.put(`/tasks/${id}`, data),
  delete: (id) => api.delete(`/tasks/${id}`),
  bulkAssign: (data) => api.post('/tasks/bulk-assign', data),
  autoAssign: (data) => api.post('/tasks/auto-assign', data),
  assign: (id, data) => api.post(`/tasks/${id}/assign`, data),
  start: (id) => api.put(`/tasks/${id}/start`),
  complete: (id, data) => api.put(`/tasks/${id}/complete`, data),
  fail: (id, data) => api.put(`/tasks/${id}/fail`, data),
  acknowledge: (id) => api.put(`/tasks/${id}/acknowledge`),
  addEvidence: (id, formData) => api.post(`/tasks/${id}/evidence`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  getComments: (id) => api.get(`/tasks/${id}/comments`),
  addComment: (id, data) => api.post(`/tasks/${id}/comments`, data),
}

// Excel Import API
export const excelApi = {
  import: (formData) => api.post('/excel-import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  getAll: (params) => api.get('/excel-import', { params }),
  get: (id) => api.get(`/excel-import/${id}`),
  process: (id, data) => api.post(`/excel-import/${id}/process`, data),
  update: (id, data) => api.put(`/excel-import/${id}`, data),
  delete: (id) => api.delete(`/excel-import/${id}`),
  downloadTemplate: () => api.get('/excel-import/template/download', { responseType: 'blob' }),
}

// WhatsApp API
export const whatsappApi = {
  sendBulk: (data) => api.post('/whatsapp/send-bulk', data),
  sendToClient: (data) => api.post('/whatsapp/send-to-client', data),
  getMessages: (params) => api.get('/whatsapp/messages', { params }),
}

// Dashboard API
export const dashboardApi = {
  stats: (params) => api.get('/dashboard/stats', { params }),
  mapData: (params) => api.get('/dashboard/map-data', { params }),
  agentPerformance: (params) => api.get('/dashboard/agent-performance', { params }),
}

// Reports API
export const reportsApi = {
  getAll: (params) => api.get('/reports', { params }),
  generate: (data) => api.post('/reports/generate', data),
  delete: (id) => api.delete(`/reports/${id}`),
  schedules: () => api.get('/reports/schedules'),
  storeSchedule: (data) => api.post('/reports/schedules', data),
  updateSchedule: (id, data) => api.put(`/reports/schedules/${id}`, data),
  deleteSchedule: (id) => api.delete(`/reports/schedules/${id}`),
}

// Performance API
export const performanceApi = {
  daily: (params) => api.get('/performance/daily', { params }),
  mine: (params) => api.get('/performance/my', { params }),
  generate: (data) => api.post('/performance/generate', data),
  myReports: (params) => api.get('/performance/my-reports', { params }),
  reportDetail: (id) => api.get(`/performance/report/${id}`),
  downloadReport: (id) =>
    api.get(`/performance/report/${id}/download`, { responseType: 'blob' }),
}