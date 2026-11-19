import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../core/tema.dart';

/// Cámara de escaneo de códigos de barras.
///
/// Sólo se monta mientras el usuario mantiene el escáner abierto, así la
/// cámara se libera al cerrarlo.
class EscanerCamara extends StatefulWidget {
  const EscanerCamara({super.key, required this.alLeer, required this.alCerrar});

  final Future<void> Function(String codigo) alLeer;
  final VoidCallback alCerrar;

  @override
  State<EscanerCamara> createState() => _EscanerCamaraState();
}

class _EscanerCamaraState extends State<EscanerCamara> {
  late final MobileScannerController _controlador = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [
      BarcodeFormat.ean13,
      BarcodeFormat.ean8,
      BarcodeFormat.upcA,
      BarcodeFormat.upcE,
      BarcodeFormat.code128,
      BarcodeFormat.code39,
      BarcodeFormat.qrCode,
    ],
  );

  bool _procesando = false;

  @override
  void initState() {
    super.initState();
    _iniciar();
  }

  Future<void> _iniciar() async {
    try {
      await _controlador.start();
    } catch (_) {
      // Si la cámara no está disponible el widget muestra el error del paquete.
    }
  }

  @override
  void dispose() {
    _controlador.dispose();
    super.dispose();
  }

  Future<void> _alDetectar(BarcodeCapture captura) async {
    if (_procesando) return;
    final codigo = captura.barcodes
        .map((b) => b.rawValue)
        .firstWhere((valor) => valor != null && valor.isNotEmpty, orElse: () => null);
    if (codigo == null) return;

    _procesando = true;
    await widget.alLeer(codigo);
    await Future<void>.delayed(const Duration(milliseconds: 900));
    if (mounted) _procesando = false;
  }

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: _controlador,
            onDetect: _alDetectar,
            fit: BoxFit.cover,
            errorBuilder: (context, error) => Container(
              color: Colors.black87,
              padding: const EdgeInsets.all(20),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.no_photography_outlined, color: Colors.white70, size: 32),
                  const SizedBox(height: 10),
                  Text(
                    'No se pudo abrir la cámara.\n${error.errorCode.name}',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ],
              ),
            ),
          ),
          const _MarcoEscaneo(),
          Positioned(
            top: 10,
            right: 10,
            child: Column(
              children: [
                _BotonRedondo(
                  icono: Icons.close,
                  alTocar: widget.alCerrar,
                ),
                const SizedBox(height: 10),
                ValueListenableBuilder<MobileScannerState>(
                  valueListenable: _controlador,
                  builder: (context, estado, _) => _BotonRedondo(
                    icono: estado.torchState == TorchState.on
                        ? Icons.flashlight_on
                        : Icons.flashlight_off,
                    alTocar: () => _controlador.toggleTorch(),
                  ),
                ),
              ],
            ),
          ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 12,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Text(
                  'Apunte al código de barras',
                  style: TextStyle(color: Colors.white, fontSize: 12),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MarcoEscaneo extends StatelessWidget {
  const _MarcoEscaneo();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 220,
        height: 130,
        decoration: BoxDecoration(
          border: Border.all(color: AppColores.verde.withValues(alpha: 0.9), width: 3),
          borderRadius: BorderRadius.circular(14),
        ),
      ),
    );
  }
}

class _BotonRedondo extends StatelessWidget {
  const _BotonRedondo({required this.icono, required this.alTocar});

  final IconData icono;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.black54,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: alTocar,
        child: Padding(
          padding: const EdgeInsets.all(9),
          child: Icon(icono, color: Colors.white, size: 20),
        ),
      ),
    );
  }
}
