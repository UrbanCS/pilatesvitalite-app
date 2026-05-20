import { getPublicConfig, removeSubscription, saveSubscription } from './api';
import type { PushStatus } from '../types';

function urlBase64ToUint8Array(base64String: string) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let index = 0; index < rawData.length; index += 1) {
    outputArray[index] = rawData.charCodeAt(index);
  }

  return outputArray;
}

export function pushIsSupported() {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

export async function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    return null;
  }

  return navigator.serviceWorker.register('./sw.js');
}

export async function readPushStatus(): Promise<PushStatus> {
  if (!pushIsSupported()) {
    return 'unsupported';
  }

  if (Notification.permission === 'denied') {
    return 'denied';
  }

  const registration = await navigator.serviceWorker.ready;
  const current = await registration.pushManager.getSubscription();

  if (current) {
    return 'subscribed';
  }

  return Notification.permission;
}

export async function subscribeToPush() {
  if (!pushIsSupported()) {
    throw new Error('Les notifications push ne sont pas supportées par ce navigateur.');
  }

  const permission = await Notification.requestPermission();
  if (permission !== 'granted') {
    throw new Error("La permission de notification n'a pas été accordée.");
  }

  const publicConfig = await getPublicConfig();
  if (!publicConfig.vapidPublicKey) {
    throw new Error("La clé VAPID publique n'est pas configurée.");
  }

  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(publicConfig.vapidPublicKey)
  });

  await saveSubscription(subscription);
  return subscription;
}

export async function unsubscribeFromPush() {
  if (!pushIsSupported()) {
    return;
  }

  const registration = await navigator.serviceWorker.ready;
  const current = await registration.pushManager.getSubscription();

  if (!current) {
    return;
  }

  await removeSubscription(current.endpoint);
  await current.unsubscribe();
}
