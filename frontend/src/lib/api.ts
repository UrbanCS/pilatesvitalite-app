import type { LatestResponse, PublicConfig } from '../types';

const API_BASE = (import.meta.env.VITE_API_BASE || './api').replace(/\/$/, '');

async function requestJson<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    headers: {
      Accept: 'application/json',
      ...(init?.headers || {})
    },
    ...init
  });

  let payload: unknown = null;
  try {
    payload = await response.json();
  } catch (error) {
    payload = null;
  }

  if (!response.ok) {
    const message =
      payload && typeof payload === 'object' && 'error' in payload
        ? String((payload as { error: string }).error)
        : 'Une erreur réseau est survenue.';
    throw new Error(message);
  }

  return payload as T;
}

export function getLatestContent() {
  return requestJson<LatestResponse>('/latest.php');
}

export function getPublicConfig() {
  return requestJson<PublicConfig>('/config.php');
}

export function saveSubscription(subscription: PushSubscription) {
  return requestJson<{ ok: boolean }>('/subscribe.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      ...subscription.toJSON(),
      userAgent: navigator.userAgent
    })
  });
}

export function removeSubscription(endpoint: string) {
  return requestJson<{ ok: boolean }>('/unsubscribe.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ endpoint })
  });
}
