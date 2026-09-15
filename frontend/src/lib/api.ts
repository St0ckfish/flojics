import axios, { isAxiosError } from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
  },
})

export function apiErrorMessage(error: unknown): string {
  if (isAxiosError(error)) {
    const message = error.response?.data?.message

    if (typeof message === 'string' && message !== '') {
      return message
    }

    if (error.response?.status === 404) {
      return 'Ticket was not found.'
    }

    if (!error.response) {
      return 'Cannot reach the API. Is php artisan serve running on port 8000?'
    }
  }

  return 'Something went wrong.'
}
