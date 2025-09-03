<footer class="main-footer">
    <div class="footer-content">
        <div class="footer-logo">
            <i class="fas fa-utensils footer-icon"></i>
            <span class="footer-brand-name">AROMA S.A.C</span>
        </div>
        
        <div class="footer-info">
            <p class="copyright-text">
                © <?php echo date("Y"); ?> Aroma S.A.C. Todos los derechos reservados.
            </p>
            <p class="system-info">
                Sistema de Gestión Empresarial
            </p>
        </div>
    </div>
</footer>

<style>
/* Estilos para el Footer */
.main-footer {
    background-color: #2c3e50; /* Un gris oscuro, elegante y profesional */
    color: #ecf0f1; /* Gris muy claro para el texto */
    text-align: center;
    padding: 30px 20px;
    margin-top: 50px;
    box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.1);
    font-family: 'Poppins', sans-serif;
    transition: background-color 0.3s ease;
}

.main-footer:hover {
    background-color: #34495e; /* Un tono más claro al pasar el cursor */
}

.footer-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    max-width: 1200px;
    margin: 0 auto;
}

.footer-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    padding: 10px 25px;
    background-color: rgba(255, 255, 255, 0.08);
    border-radius: 50px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(5px);
    transition: background-color 0.3s ease;
}

.footer-logo:hover {
    background-color: rgba(255, 255, 255, 0.15);
}

.footer-icon {
    font-size: 1.5rem;
    margin-right: 10px;
    color: #2ecc71; /* Verde esmeralda para un toque de color */
    transition: transform 0.3s ease;
}

.footer-logo:hover .footer-icon {
    transform: rotate(5deg) scale(1.1);
}

.footer-brand-name {
    font-size: 1.2rem;
    font-weight: 600;
    letter-spacing: 1px;
    color: #fff;
}

.footer-info {
    margin-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 15px;
    text-align: center;
}

.copyright-text {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 300;
    opacity: 0.8;
}

.system-info {
    margin: 5px 0 0;
    font-size: 0.8rem;
    opacity: 0.6;
    font-style: italic;
}

/* Responsivo para móviles */
@media (max-width: 768px) {
    .main-footer {
        padding: 20px 15px;
    }
    .footer-logo {
        flex-direction: column;
        padding: 10px;
        border-radius: 10px;
    }
    .footer-icon {
        margin-right: 0;
        margin-bottom: 5px;
    }
    .footer-brand-name {
        font-size: 1rem;
    }
    .copyright-text {
        font-size: 0.85rem;
    }
}
</style>