const API_BASE_URL = 'http://localhost:8080/api/v1';

export async function getHealth(): Promise<unknown> {
  const response = await fetch(`${API_BASE_URL}/health`);
  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }
  return response.json();
}
