const warningApi = getExtensionApi();
const params = new URLSearchParams(window.location.search);
const target = params.get('target') || '';
const reasons = JSON.parse(params.get('reasons') || '[]');

document.getElementById('targetUrl').textContent = target;
const list = document.getElementById('warningReasons');
reasons.forEach((reason) => {
  const item = document.createElement('li');
  item.textContent = reason;
  list.appendChild(item);
});

document.getElementById('goBack').addEventListener('click', () => {
  history.back();
});

document.getElementById('continueAnyway').addEventListener('click', async () => {
  await warningApi.runtime.sendMessage({ type: 'CONTINUE_TO_SITE', url: target });
  window.location.assign(target);
});

