/**
 * ==============================================================================
 * SYSTEM ISHIMURA — CLIENT SIDE ENGINE (CORE JS)
 * ==============================================================================
 */

document.addEventListener("DOMContentLoaded", function() {
    console.log("[+] Модернизированное ядро Hatsumi Web-UI успешно запущено.");

    // Симуляция фонового события: через 4 секунды включаем синее мигание для Arachna и Lamia
    setTimeout(() => {
        triggerModuleActivity('arachna');
        triggerModuleActivity('lamia');
    }, 4000);
});

function triggerModuleActivity(moduleName) {
    const menuItem = document.getElementById(`menu-item-${moduleName}`);
    if (menuItem && !menuItem.classList.contains('active')) {
        menuItem.classList.add('has-activity');
        console.log(`[!] Модуль [${moduleName.toUpperCase()}] сигнализирует о новой активности.`);
    }
}

function clearModuleActivity(moduleName) {
    const menuItem = document.getElementById(`menu-item-${moduleName}`);
    if (menuItem) {
        menuItem.classList.remove('has-activity');
        console.log(`[*] Сигналы активности модуля [${moduleName.toUpperCase()}] успешно сброшены.`);
    }
}
