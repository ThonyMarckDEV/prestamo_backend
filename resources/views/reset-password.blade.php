<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md border-t-4 border-red-600">
        <h2 class="text-2xl font-bold mb-6 text-center text-red-600">Cambiar Contraseña</h2>

        @if (session('success'))
            <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded">
                {{ session('success') }}
            </div>
            <div class="text-center">
                <a href="{{ config('app.front_url') }}" class="inline-block bg-red-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-700 transition-colors">
                    Ir a Iniciar Sesión
                </a>
            </div>
        @elseif (isset($error) || session('error'))
            <div class="mb-6 p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 rounded">
                {{ $error ?? session('error') }}
            </div>
            <div class="text-center">
                <a href="{{ config('app.front_url') }}" class="text-sm text-red-500 hover:underline">Volver al inicio de sesión</a>
            </div>
        @else
            <form method="POST" action="{{ route('password.reset.submit', ['idUsuario' => $idUsuario, 'token' => $token]) }}" class="space-y-6">
                @csrf
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Nueva Contraseña</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                        required
                    >
                    @error('password')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar Contraseña</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                        required
                    >
                    @error('password_confirmation')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <button
                    type="submit"
                    class="w-full bg-red-600 text-white font-semibold py-2 rounded-lg hover:bg-red-700 transition-colors"
                >
                    Cambiar Contraseña
                </button>
            </form>
            <div class="mt-4 text-center">
                <a href="{{ config('app.front_url') }}" class="text-sm text-red-500 hover:underline">Volver al inicio de sesión</a>
            </div>
        @endif
    </div>
</body>
</html>