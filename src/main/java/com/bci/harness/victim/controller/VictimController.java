package com.bci.harness.victim.controller;

import com.bci.harness.victim.entity.Victim;
import com.bci.harness.victim.service.VictimService;
import com.bci.harness.victim.service.VictimValidator;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.validation.Valid;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.web.authentication.logout.SecurityContextLogoutHandler;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;

@Controller
public class VictimController {
    private VictimService victimService;

    private VictimValidator victimValidator;

    private static final Logger LOGGER = LoggerFactory.getLogger(VictimController.class);


    @Autowired
    public VictimController(VictimService victimService, VictimValidator victimValidator) {
        this.victimService = victimService;
        this.victimValidator = victimValidator;
    }

    @GetMapping("/")
    public String indexAction(Model model) {
        LOGGER.info("home!");
        return "main";
    }

    @GetMapping("/account/registration")
    public String registrationAction(Model model) {
        model.addAttribute("victim", new Victim());
        return "account/registration";
    }

    @PostMapping("/account/registration")
    public String postRegistrationAction(@Valid @ModelAttribute("victim") Victim victim, BindingResult result, Model model) {
        victimValidator.validate(victim, result);
        if (result.hasErrors()) {
            return "account/registration";
        }

        victimService.save(victim);
        victimService.autologin(victim.getUsername(), victim.getPasswordConfirm());
        return "redirect:/";
    }

    @RequestMapping(value = "/account/login", method = RequestMethod.GET)
    public String login(Model model, String error, String logout, String sessionTimeout) {
        if (error != null) {
            model.addAttribute("error", "Your username and password is invalid.");
        } else if (sessionTimeout != null) {
            model.addAttribute("error", "You have been logged out due to inactivity.");
        }

        if (logout != null)
            model.addAttribute("message", "You have been logged out successfully.");

        return "account/login";
    }

    @RequestMapping(value = "/account/logout", method = RequestMethod.GET)
    public String logout(HttpServletRequest request, HttpServletResponse response) {
        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        if (auth != null) {
            new SecurityContextLogoutHandler().logout(request, response, auth);
        }

        SecurityContextHolder.getContext().setAuthentication(null);

        if (request.getParameter("sessionTimeout") != null) {
            return "redirect:/account/login?sessionTimeout";
        }

        return "redirect:/account/login";
    }

    @GetMapping("/account/forgotten-password")
    public String forgottenPasswordAction() {
        return "account/forgotten-password";
    }
}
