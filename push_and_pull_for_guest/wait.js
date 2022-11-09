
//  подписываемся на события для обработки сообщений

BX.PULL.subscribe({
    moduleId: 'main', //  нужно заменить на свое значение,
    command: 'hello',
    callback: function (params, extra, command) {
        alert('Получено сообщение по websocket, подробности в консоли браузера');
        console.warn('Receive message:', params.message.text)
    }.bind(this)
});