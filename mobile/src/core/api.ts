import type { Row, Session, SendError } from './types';
export function normalizeEndpoint(value: string, allowHttp = false): string {
  const url = new URL(value.trim());
  if (url.protocol !== 'https:' && !(allowHttp && url.protocol === 'http:')) throw new Error('Укажите адрес с HTTPS');
  if (url.username || url.password || url.hash) throw new Error('Укажите адрес портала без пароля и фрагмента');
  url.search = '';
  if (/\/(?:index|demo|control)\.php$/.test(url.pathname))url.pathname=url.pathname.replace(/\/[^/]+\.php$/,'/demo-api.php');
  if (!url.pathname.endsWith('.php')) url.pathname = url.pathname.replace(/\/$/, '') + '/demo-api.php';
  return url.toString();
}
export class ApiError extends Error implements SendError { constructor(public status: number, public code: string, message: string) { super(message); } }
export class Api {
  session: Session | null = null;
  csrf = '';
  private refreshing: Promise<void> | null = null;
  constructor(public endpoint: string, private native: boolean, private save: (session: Session | null) => Promise<void>) {}
  async request(path: string, method = 'GET', body?: Row | FormData, replay = true): Promise<Row> {
    if (!this.native && !this.csrf && method !== 'GET') this.csrf = String((await this.request('/v1/csrf')).csrf ?? '');
    const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), body instanceof FormData ? 45000 : 20000);
    const headers: Record<string,string> = { Accept: 'application/json', 'X-Choppro-Client': this.native ? 'native' : 'web' };
    if (this.session) headers.Authorization = 'Bearer ' + this.session.access_token;
    if (this.csrf) headers['X-CSRF-Token'] = this.csrf;
    if (body && !(body instanceof FormData)) headers['Content-Type'] = 'application/json';
    let response: Response;
    try { response = await fetch(this.endpoint + (this.endpoint.includes('?') ? '&' : '?') + 'space=mobile&path=' + encodeURIComponent(path), { method, headers, credentials: this.native ? 'omit' : 'include', body: body ? body instanceof FormData ? body : JSON.stringify(body) : undefined, signal: controller.signal }); }
    catch { throw new ApiError(0,'NETWORK','Нет связи с сервером. Запись сохранена на устройстве.'); }
    finally { clearTimeout(timeout); }
    const data = await response.json().catch(() => ({ message: 'Сервер вернул некорректный ответ' })) as Row;
    if (response.status === 401 && this.session && replay && path !== '/v1/auth/refresh') {
      await this.refresh(); return this.request(path, method, body, false);
    }
    if (!response.ok) throw new ApiError(response.status, String(data.code ?? 'ERROR'), String(data.message ?? 'Не удалось выполнить действие'));
    return data;
  }
  async use(session: Session): Promise<void> { this.session = session; this.csrf = session.csrf ?? ''; await this.save(session); }
  async refresh(): Promise<void> {
    if (this.refreshing) return this.refreshing;
    this.refreshing = (async () => {
      const current = this.session;
      if (!current) throw new ApiError(401,'AUTH_REQUIRED','Войдите в систему');
      try { await this.use(await this.request('/v1/auth/refresh','POST',{ refresh_token: current.refresh_token },false) as Session); }
      catch (error) { if ((error as SendError).status === 401) { this.session = null; await this.save(null); } throw error; }
    })().finally(() => { this.refreshing = null; });
    return this.refreshing;
  }
}
